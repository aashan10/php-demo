<?php

declare(strict_types=1);

namespace App\SparkComponents;

use App\Models\Post;
use Elementary\Http\Request;
use Elementary\Spark\SparkComponent;
use Elementary\Utils\ParameterBag;
use Elementary\Validation\Validator;

class CreatePostForm extends SparkComponent
{
    public string $title = '';
    public string $content = '';
    public bool $postCreated = false;
    public ?ParameterBag $errors = null;

    protected array $rules = [
        'title' => ['required', 'minLength:5'],
        'content' => ['required', 'minLength:20'],
    ];

    public function save(Request $request): void
    {
        $validator = new Validator($this->getPublicProperties(), $this->rules);

        if ($validator->fails()) {
            // The validation errors will be handled and displayed automatically by Spark.
            // We just need to trigger the validation.
            $this->errors = new ParameterBag( $this->validate() );
            return;
        }

        // An authenticated user is required to create a post.
        if (!$request->user) {
            // In a real app, you might emit an error event.
            // For now, we'll just stop execution.
            return;
        }

        Post::create([
            'title' => $this->title,
            'content' => $this->content,
            'user_id' => $request->user->getId(),
        ]);

        $this->postCreated = true;
        $this->emit('postCreated', ['title' => $this->title]);

        // Reset form fields
        $this->title = '';
        $this->content = '';
    }

    public function render(): string
    {
        $cachePath = $this->getCompiledTemplate();
        $componentData = $this->getPublicProperties();
        extract($componentData);
        ob_start();
        include $cachePath;
        return ob_get_clean();
    }
}
