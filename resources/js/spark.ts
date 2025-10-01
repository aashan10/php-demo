import { createSignal, createEffect } from './reactivity';

interface ComponentData {
    id: string;
    name: string; // Component class name (FQN)
    data: Record<string, any>;
    checksum: string;
}

interface ComponentElement extends HTMLElement {
    __spark?: SparkComponent;
}

interface SparkEvent {
    name: string;
    data: any;
    timestamp: number;
}

class SparkComponent {
    public element: ComponentElement;
    public data: Record<string, any> = {};
    public checksum: string = '';
    public id: string;
    public name: string; // Component class name
    private signals: Map<string, [() => any, (value: any) => void]> = new Map();
    private updateQueue: Set<string> = new Set();
    private debounceTimeout: number | null = null;

    constructor(element: ComponentElement) {
        this.element = element;
        this.id = element.getAttribute('spark:id') || '';
        
        console.log('[Spark] Initializing component', {
            id: this.id,
            element: element
        });
        
        // Parse initial data
        const sparkData = element.getAttribute('spark:data');
        if (sparkData) {
            try {
                const componentData: ComponentData = JSON.parse(atob(sparkData));
                this.data = componentData.data;
                this.checksum = componentData.checksum;
                this.name = componentData.name; // Store component class name
                
                console.log('[Spark] Component data parsed successfully', {
                    id: this.id,
                    name: this.name,
                    data: this.data,
                    checksum: this.checksum
                });
            } catch (e) {
                console.error('[Spark] Failed to parse spark:data', {
                    id: this.id,
                    sparkData: sparkData,
                    error: e
                });
                throw new Error(`Failed to initialize Spark component ${this.id}: Invalid spark:data`);
            }
        } else {
            console.warn('[Spark] No spark:data attribute found', { id: this.id });
        }

        try {
            this.setupSignals();
            console.log('[Spark] Signals setup complete', { id: this.id });
        } catch (e) {
            console.error('[Spark] Failed to setup signals', { id: this.id, error: e });
            throw e;
        }
        
        try {
            this.setupEventListeners();
            console.log('[Spark] Event listeners setup complete', { id: this.id });
        } catch (e) {
            console.error('[Spark] Failed to setup event listeners', { id: this.id, error: e });
            throw e;
        }
        
        element.__spark = this;
        console.log('[Spark] Component initialization complete', { id: this.id });
    }

    private setupSignals(): void {
        // Create reactive signals for each data property
        for (const [key, value] of Object.entries(this.data)) {
            const [get, set] = createSignal(value);
            this.signals.set(key, [get, set]);
            
            // Create effect to detect changes and queue updates
            createEffect(() => {
                const currentValue = get();
                if (currentValue !== this.data[key]) {
                    this.data[key] = currentValue;
                    this.updateChecksum(); // Update checksum when data changes
                    this.queueUpdate(key);
                }
            });
        }
    }

    public set(property: string, value: any): void {
        const signal = this.signals.get(property);
        if (signal) {
            signal[1](value);
        } else {
            // Create new signal for dynamic property
            const [get, set] = createSignal(value);
            this.signals.set(property, [get, set]);
            set(value);
            this.data[property] = value;
            this.updateChecksum(); // Update checksum for dynamic properties
        }
        this.queueUpdate(property);
    }

    public get(property: string): any {
        const signal = this.signals.get(property);
        return signal ? signal[0]() : this.data[property];
    }

    /**
     * Update checksum to match current data state
     */
    private updateChecksum(): void {
        // Simple SHA-256 implementation using Web Crypto API
        const dataString = JSON.stringify(this.data) + this.name;
        
        // For now, use a simple hash - in production, you'd want proper SHA-256
        let hash = 0;
        for (let i = 0; i < dataString.length; i++) {
            const char = dataString.charCodeAt(i);
            hash = ((hash << 5) - hash) + char;
            hash = hash & hash; // Convert to 32-bit integer
        }
        
        this.checksum = 'client_' + Math.abs(hash).toString(16);
        
        console.log('[Spark] Checksum updated', {
            id: this.id,
            data: this.data,
            checksum: this.checksum
        });
    }

    private queueUpdate(property: string): void {
        this.updateQueue.add(property);
        this.debounceUpdate();
    }

    private debounceUpdate(): void {
        if (this.debounceTimeout) {
            clearTimeout(this.debounceTimeout);
        }
        
        this.debounceTimeout = window.setTimeout(() => {
            this.syncToServer();
        }, 150);
    }

    private async syncToServer(): Promise<void> {
        if (this.updateQueue.size === 0) return;

        console.log('[Spark] Syncing to server', {
            id: this.id,
            updateQueue: Array.from(this.updateQueue)
        });

        const updates: Record<string, any> = {};
        for (const property of this.updateQueue) {
            updates[property] = this.data[property];
        }
        this.updateQueue.clear();

        try {
            const response = await this.makeRequest({
                updates,
                validate: true
            });

            console.log('[Spark] Sync response received', {
                id: this.id,
                response: response
            });

            this.handleResponse(response);
        } catch (error) {
            console.error('[Spark] Sync failed', {
                id: this.id,
                updates: updates,
                error: error
            });
            
            // Show user-friendly error
            this.showError(`Sync failed: ${error.message || 'Unknown error'}`);
        }
    }

    public async callMethod(method: string, params: any[] = []): Promise<void> {
        console.log('[Spark] Calling method', {
            id: this.id,
            method: method,
            params: params
        });
        
        try {
            const response = await this.makeRequest({
                method,
                params
            });

            console.log('[Spark] Method call response received', {
                id: this.id,
                method: method,
                response: response
            });

            this.handleResponse(response);
        } catch (error) {
            console.error('[Spark] Method call failed', {
                id: this.id,
                method: method,
                params: params,
                error: error
            });
            
            // Show user-friendly error
            this.showError(`Method '${method}' failed: ${error.message || 'Unknown error'}`);
        }
    }

    private async makeRequest(payload: any): Promise<any> {
        const requestPayload = {
            componentId: this.id,
            componentName: this.name, // Send component class name
            checksum: this.checksum,
            serverMemo: {
                data: this.data, // Send current component data
                checksum: this.checksum
            },
            ...payload
        };

        console.log('[Spark] Making request', {
            id: this.id,
            url: '/spark/message',
            payload: requestPayload
        });

        try {
            const response = await fetch('/spark/message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(requestPayload)
            });

            console.log('[Spark] HTTP response received', {
                id: this.id,
                status: response.status,
                statusText: response.statusText,
                ok: response.ok
            });

            if (!response.ok) {
                let errorMessage = `HTTP ${response.status}: ${response.statusText}`;
                
                try {
                    const errorData = await response.json();
                    if (errorData.error) {
                        errorMessage = errorData.error;
                    }
                } catch (e) {
                    // Couldn't parse error response
                }
                
                throw new Error(errorMessage);
            }

            const data = await response.json();
            console.log('[Spark] Response data parsed', {
                id: this.id,
                data: data
            });
            
            return data;
        } catch (error) {
            console.error('[Spark] Request failed', {
                id: this.id,
                url: '/spark/message',
                payload: requestPayload,
                error: error
            });
            throw error;
        }
    }

    private handleResponse(response: any): void {
        // Update component data
        if (response.data) {
            for (const [key, value] of Object.entries(response.data)) {
                if (this.data[key] !== value) {
                    this.data[key] = value;
                    const signal = this.signals.get(key);
                    if (signal) {
                        signal[1](value);
                    }
                }
            }
        }

        // Update checksum
        if (response.checksum) {
            this.checksum = response.checksum;
        }

        // Update DOM
        if (response.html) {
            this.updateDOM(response.html);
        }

        // Handle events
        if (response.events) {
            this.handleEvents(response.events);
        }

        // Handle validation errors
        if (response.errors) {
            this.handleValidationErrors(response.errors);
        }
    }

    private updateDOM(html: string): void {
        // Create a temporary container
        const temp = document.createElement('div');
        temp.innerHTML = html;
        const newContent = temp.firstElementChild;

        if (newContent) {
            // Preserve form inputs and focus
            this.preserveFormState(this.element, newContent as HTMLElement);
            
            // Replace content but keep spark attributes
            this.element.innerHTML = newContent.innerHTML;
            
            // Re-setup event listeners for new elements
            this.setupEventListeners();
        }
    }

    private preserveFormState(oldElement: HTMLElement, newElement: HTMLElement): void {
        // Preserve input values using spark:model attributes for better matching
        const oldInputs = oldElement.querySelectorAll('input[spark\\:model], textarea[spark\\:model], select[spark\\:model]');
        
        oldInputs.forEach((oldInput) => {
            if (oldInput instanceof HTMLInputElement || oldInput instanceof HTMLTextAreaElement || oldInput instanceof HTMLSelectElement) {
                const sparkModel = oldInput.getAttribute('spark:model');
                if (sparkModel) {
                    // Find corresponding input in new element by spark:model attribute
                    const newInput = newElement.querySelector(`[spark\\:model="${sparkModel}"]`) as HTMLInputElement;
                    
                    if (newInput) {
                        if (oldInput.type === 'checkbox' || oldInput.type === 'radio') {
                            newInput.checked = (oldInput as HTMLInputElement).checked;
                        } else {
                            newInput.value = oldInput.value;
                        }
                        
                        console.log('[Spark] Preserved form state', {
                            sparkModel: sparkModel,
                            oldValue: oldInput.value,
                            newValue: newInput.value
                        });
                    }
                }
            }
        });

        // Preserve focus using spark:model for better matching
        const focusedElement = oldElement.querySelector(':focus') as HTMLInputElement;
        if (focusedElement && focusedElement.hasAttribute('spark:model')) {
            const sparkModel = focusedElement.getAttribute('spark:model');
            const newFocusTarget = newElement.querySelector(`[spark\\:model="${sparkModel}"]`) as HTMLElement;
            
            if (newFocusTarget) {
                setTimeout(() => {
                    newFocusTarget.focus();
                    console.log('[Spark] Restored focus to', sparkModel);
                }, 0);
            }
        }
    }

    private setupEventListeners(): void {
        // Set up spark:model listeners
        this.element.querySelectorAll('[spark\\:model]').forEach(element => {
            const property = element.getAttribute('spark:model');
            if (property && element instanceof HTMLInputElement) {
                // Use blur event for text inputs to avoid too many updates
                const eventType = (element.type === 'text' || element.type === 'email' || element.type === 'password') ? 'blur' : 'input';
                
                element.addEventListener(eventType, (e) => {
                    const target = e.target as HTMLInputElement;
                    const value = target.type === 'checkbox' ? target.checked : target.value;
                    this.set(property, value);
                });
            }
        });

        // Set up spark:click listeners
        this.element.querySelectorAll('[spark\\:click]').forEach(element => {
            const method = element.getAttribute('spark:click');
            if (method) {
                element.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.callMethod(method);
                });
            }
        });

        // Set up other spark event listeners
        ['change', 'blur', 'keydown', 'submit'].forEach(eventType => {
            this.element.querySelectorAll(`[spark\\:${eventType}]`).forEach(element => {
                const method = element.getAttribute(`spark:${eventType}`);
                if (method) {
                    element.addEventListener(eventType, (e) => {
                        if (eventType === 'submit') {
                            e.preventDefault();
                        }
                        this.callMethod(method);
                    });
                }
            });
        });

        // Set up spark:on-* event listeners for re-rendering
        const sparkOnAttributes = Array.from(this.element.querySelectorAll('*'))
            .flatMap(el => Array.from(el.attributes))
            .filter(attr => attr.name.startsWith('spark:on-'));

        sparkOnAttributes.forEach(attr => {
            const element = attr.ownerElement;
            const eventType = attr.name.substring(9); // Remove 'spark:on-' prefix
            const action = attr.value;
            
            if (element && action === 'render') {
                element.addEventListener(eventType, (e) => {
                    console.log(`[Spark] ${eventType} event triggered re-render for component ${this.id}`);
                    this.syncToServer();
                });
            } else if (element && action !== 'render') {
                // Support method calls with spark:on-* syntax
                element.addEventListener(eventType, (e) => {
                    if (eventType === 'submit') {
                        e.preventDefault();
                    }
                    console.log(`[Spark] ${eventType} event triggered method ${action} for component ${this.id}`);
                    this.callMethod(action);
                });
            }
        });
    }

    private handleEvents(events: SparkEvent[]): void {
        events.forEach(event => {
            // Dispatch custom event for other components to listen
            const customEvent = new CustomEvent(`spark:${event.name}`, {
                detail: event.data,
                bubbles: true
            });
            document.dispatchEvent(customEvent);
        });
    }

    private handleValidationErrors(errors: Record<string, string[]>): void {
        // Clear existing error messages
        this.element.querySelectorAll('.spark-error').forEach(el => el.remove());

        // Display new errors
        for (const [field, messages] of Object.entries(errors)) {
            const input = this.element.querySelector(`[spark\\:model="${field}"]`);
            if (input && messages.length > 0) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'spark-error text-red-500 text-sm mt-1';
                errorDiv.textContent = messages[0];
                input.parentElement?.appendChild(errorDiv);
            }
        }
    }
    
    /**
     * Show error message to user
     */
    private showError(message: string): void {
        // Create a simple error toast
        const errorDiv = document.createElement('div');
        errorDiv.className = 'spark-error-toast';
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ef4444;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            z-index: 10000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 400px;
        `;
        errorDiv.textContent = message;
        
        document.body.appendChild(errorDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.parentNode.removeChild(errorDiv);
            }
        }, 5000);
        
        console.error('[Spark] Error displayed to user:', message);
    }
}

class Spark {
    private components: Map<string, SparkComponent> = new Map();

    public start(): void {
        this.scanForComponents();
        this.setupGlobalEventListeners();
    }

    private scanForComponents(): void {
        console.log('[Spark] Scanning for components');
        
        const elements = document.querySelectorAll('[spark\\:id]');
        console.log(`[Spark] Found ${elements.length} potential components`);
        
        elements.forEach(element => {
            const id = element.getAttribute('spark:id');
            if (id && !this.components.has(id)) {
                try {
                    console.log(`[Spark] Initializing component: ${id}`);
                    const component = new SparkComponent(element as ComponentElement);
                    this.components.set(id, component);
                    console.log(`[Spark] Component initialized successfully: ${id}`);
                } catch (error) {
                    console.error(`[Spark] Failed to initialize component: ${id}`, error);
                }
            }
        });
        
        console.log(`[Spark] Total components registered: ${this.components.size}`);
    }
    
    /**
     * Show error message to user
     */
    private showError(message: string): void {
        // Create a simple error toast
        const errorDiv = document.createElement('div');
        errorDiv.className = 'spark-error-toast';
        errorDiv.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            background: #ef4444;
            color: white;
            padding: 12px 20px;
            border-radius: 6px;
            z-index: 10000;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-width: 400px;
        `;
        errorDiv.textContent = message;
        
        document.body.appendChild(errorDiv);
        
        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (errorDiv.parentNode) {
                errorDiv.parentNode.removeChild(errorDiv);
            }
        }, 5000);
        
        console.error('[Spark] Error displayed to user:', message);
    }

    private setupGlobalEventListeners(): void {
        // Re-scan for components on DOM changes
        const observer = new MutationObserver(() => {
            this.scanForComponents();
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    public getComponent(id: string): SparkComponent | undefined {
        return this.components.get(id);
    }

    public emit(event: string, data?: any): void {
        const customEvent = new CustomEvent(`spark:${event}`, {
            detail: data,
            bubbles: true
        });
        document.dispatchEvent(customEvent);
    }
}

// Initialize Spark
console.log('[Spark] Initializing Spark system');
const spark = new Spark();

// Auto-start when DOM is ready
if (document.readyState === 'loading') {
    console.log('[Spark] DOM not ready, waiting for DOMContentLoaded');
    document.addEventListener('DOMContentLoaded', () => {
        console.log('[Spark] DOM ready, starting Spark');
        spark.start();
    });
} else {
    console.log('[Spark] DOM already ready, starting Spark immediately');
    spark.start();
}

// Global Spark object
(window as any).Spark = spark;
console.log('[Spark] Global Spark object attached to window');

// Add global error handler for debugging
window.addEventListener('error', (event) => {
    if (event.error && event.error.message && event.error.message.includes('Spark')) {
        console.error('[Spark] Global error caught:', event.error);
    }
});

export default spark;