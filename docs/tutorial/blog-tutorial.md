# Tutorial: Building a Simple Blog

Welcome to the Elementary framework! This tutorial will guide you through building a simple blog from scratch. You'll learn how to use many of the framework's core features, including routing, controllers, database models, and the Cigg templating engine.

## What We'll Build

We will create a simple blog application with the following features:
- A page to list all blog posts.
- A page to display a single blog post.
- A form to create a new blog post.

## Prerequisites

Before you begin, please ensure you have followed the **[Installation and Setup Guide](../01-installation.md)** and have the Docker environment up and running.

---

## Step 1: Database and Migration

First, we need a database table to store our blog posts. We'll create a `posts` table with columns for an ID, title, content, and timestamps.

While you could create the table manually using a tool like phpMyAdmin, let's use the framework's console command system to create a migration-like script.

### Create the Command

We can use the built-in `make:command` generator to create a new command file for us. Get a shell inside the `cli` container by running:

```bash
docker-compose exec cli bash
```

Now, from within the container, run the following command:

```bash
php elementary make:command CreatePostsTable --signature=db:create-posts --description="Creates the posts table"
```

This will generate a new file at `src/Console/Commands/CreatePostsTable.php`.

### Write the SQL

Open the newly created file and add the logic to execute a `CREATE TABLE` SQL query. The `execute` method should look like this:

```php
// src/Console/Commands/CreatePostsTable.php

public function execute(array $args = []): int
{
    /** @var \Elementary\Database\Connection $db */
    $db = $this->getContainer()->get(\Elementary\Database\Connection::class);
    
    $sql = "
        CREATE TABLE IF NOT EXISTS posts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
    ";
    
    try {
        $db->getInstance()->exec($sql);
        $this->success("Table 'posts' created successfully (if it didn't exist).");
    } catch (\PDOException $e) {
        $this->error("Failed to create table: " . $e->getMessage());
        return 1; // Indicate failure
    }

    return 0; // Indicate success
}
```
*Note: We are injecting the DI container into our command to get access to the `Connection` service.*

### Run the Command

Now, run the command from within your `cli` container to create the table:

```bash
php elementary db:create-posts
```

You should see a success message, and the `posts` table will now exist in your database.

---

## Step 2: Create the Post Model

With the database table in place, we need a Model to interact with it. We can use another generator for this.

From within the `cli` container, run:

```bash
php elementary make:model Post --table=posts
```

This creates the file `src/Models/Post.php` and correctly configures it to use the `posts` table. The generated file will look like this:

```php
// src/Models/Post.php

<?php

declare(strict_types=1);

namespace App\Models;

use Elementary\Database\Model;

class Post extends Model 
{
    protected static string $table = 'posts';

    // You can define public properties here for type-hinting
    public int $id;
    public string $title;
    public string $content;
    public string $created_at;
}
```

This `Post` model is now ready to be used for all database operations related to our blog posts.

---

## Step 3: Create the Post Controller

Now that we have a model, we need a controller to handle the HTTP requests related to posts. The controller will contain the logic for listing, showing, and creating posts.

Once again, we can use a generator command. From within the `cli` container, run:

```bash
php elementary make:controller PostController
```

This command creates `src/Controllers/PostController.php`. Let's open that file and add some placeholder methods for the actions our blog will need:

```php
// src/Controllers/PostController.php

<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Post;
use Elementary\Http\Controller;
use Elementary\Http\Request;
use Elementary\Http\Response;

class PostController extends Controller 
{
    /**
     * Display a listing of the posts.
     */
    public function index(): Response
    {
        // Logic to fetch and display all posts
    }

    /**
     * Show the form for creating a new post.
     */
    public function create(): Response
    {
        // Logic to show the post creation form
    }

    /**
     * Store a newly created post in the database.
     */
    public function store(Request $request): Response
    {
        // Logic to validate and save the new post
    }

    /**
     * Display the specified post.
     */
    public function show(Request $request): Response
    {
        // Logic to find and display a single post
    }
}
```

---

## Step 4: Define the Routes

With our controller and its methods defined, we need to map specific URLs to them. We do this in the `routes/web.php` file.

Open `routes/web.php` and add the following routes. It's best to place them inside the existing `auth` middleware group to ensure only logged-in users can access the blog.

```php
// routes/web.php

// ... existing routes

Router::middleware('auth')->group(function() {
    // ... existing routes inside the group

    // Blog Post Routes
    Router::get('/posts', PostController::class . '@index')->name('posts.index');
    Router::get('/posts/create', PostController::class . '@create')->name('posts.create');
    Router::post('/posts', PostController::class . '@store')->name('posts.store');
    Router::get('/posts/{id}', PostController::class . '@show')->name('posts.show');
});
```

By adding these lines, we have defined the following endpoints:
-   `GET /posts`: Will show a list of all posts.
-   `GET /posts/create`: Will show a form to create a new post.
-   `POST /posts`: Will handle the submission of the new post form.
-   `GET /posts/{id}`: Will show a single blog post based on its ID.

We also used the `name()` method to give each route a unique name, which makes it easy to generate URLs to them later.

---

## Step 5: Listing All Posts

Now we'll implement the `index` action in our `PostController` and create a view to display all the posts.

### Implement the Controller Method

Update the `index` method in `src/Controllers/PostController.php` to fetch all posts from the database and pass them to a view.

```php
// src/Controllers/PostController.php -> index()

public function index(): Response
{
    $posts = Post::query()->orderBy('created_at', 'DESC')->get();

    return $this->render('posts/index', ['posts' => $posts]);
}
```

Here, we use our `Post` model to start a query, order the results by the newest posts first, get all the results, and then pass them to the `posts/index.cigg` view.

### Create the View

Next, create the directory and file for our view: `templates/posts/index.cigg`. Add the following content:

```html
@extends('layouts/app')

@section('title')
    Blog Posts
@endsection

@section('body')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="flex justify-between items-center mb-8">
        <h1 class="text-3xl font-bold text-gray-900">All Blog Posts</h1>
        <a href="/posts/create" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            Create New Post
        </a>
    </div>

    <div class="space-y-6">
        @if (empty($posts))
            <div class="text-center py-12 bg-white rounded-lg shadow-md">
                <p class="text-gray-600">No posts have been created yet.</p>
            </div>
        @else
            @foreach ($posts as $post)
                <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-xl transition-shadow duration-300">
                    <div class="p-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-2">
                            <a href="/posts/{{ $post->id }}" class="hover:text-blue-600 transition-colors">{{ $post->title }}</a>
                        </h2>
                        <p class="text-gray-600 mb-4">
                            {{ substr($post->content, 0, 150) }}...
                        </p>
                        <div class="text-sm text-gray-500">
                            Posted on {{ date('F j, Y', strtotime($post->created_at)) }}
                        </div>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection
```

Now, if you navigate to `/posts` in your browser, you should see a (currently empty) list of blog posts.

---

## Step 6: Showing a Single Post

Let's implement the `show` action to display a single post when a user clicks on its title.

### Implement the Controller Method

Update the `show` method in `src/Controllers/PostController.php`.

```php
// src/Controllers/PostController.php -> show()

public function show(Request $request): Response
{
    $id = $request->attributes->get('id');
    $post = Post::find((int)$id);

    if (!$post) {
        // If the post doesn't exist, render a 404 error page.
        return $this->render('errors/404', []);
    }

    return $this->render('posts/show', ['post' => $post]);
}
```

This method retrieves the `id` from the URL, finds the corresponding post, and renders the `posts/show.cigg` view. If no post is found, it displays a 404 error.

### Create the View

Create the file `templates/posts/show.cigg` with the following content:

```html
@extends('layouts/app')

@section('title')
    {{ $post->title }}
@endsection

@section('body')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <div class="bg-white rounded-lg shadow-xl p-8">
        <h1 class="text-4xl font-bold text-gray-900 mb-4">{{ $post->title }}</h1>
        <p class="text-sm text-gray-500 mb-8">Posted on {{ date('F j, Y, g:i a', strtotime($post->created_at)) }}</p>
        
        <div class="prose prose-lg max-w-none text-gray-700">
            {!! nl2br(htmlspecialchars($post->content)) !!}
        </div>

        <div class="mt-12 border-t pt-6">
            <a href="/posts" class="text-blue-600 hover:underline">&larr; Back to all posts</a>
        </div>
    </div>
</div>
@endsection
```

This view displays the full title and content of the blog post. We use `nl2br()` to convert newlines in the content to `<br>` tags for proper display.

---

## Step 7: Creating New Posts (The Form)

Now, let's create the form for adding new posts.

### Implement the Controller Method

First, fill in the `create` method in `PostController`. Its only job is to display the form.

```php
// src/Controllers/PostController.php -> create()

public function create(): Response
{
    return $this->render('posts/create');
}
```

### Create the View

Create the file `templates/posts/create.cigg`. This view will contain the HTML form.

```html
@extends('layouts/app')

@section('title')
    Create New Post
@endsection

@section('body')
<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
    <h1 class="text-3xl font-bold text-gray-900 mb-8">Create a New Post</h1>

    <div class="bg-white rounded-lg shadow-md p-8">
        <form action="/posts" method="post" class="space-y-6">
            @csrf

            <div>
                <label for="title" class="block text-sm font-medium text-gray-700">Title</label>
                <input type="text" name="title" id="title" value="{{ $old['title'] ?? '' }}" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                @if ($errors->has('title'))
                    <p class="mt-2 text-sm text-red-600">{{ $errors->get('title')->get('title') }}</p>
                @endif
            </div>

            <div>
                <label for="content" class="block text-sm font-medium text-gray-700">Content</label>
                <textarea name="content" id="content" rows="10" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:text-sm">{{ $old['content'] ?? '' }}</textarea>
                @if ($errors->has('content'))
                    <p class="mt-2 text-sm text-red-600">{{ $errors->get('content')->get('content') }}</p>
                @endif
            </div>

            <div class="flex justify-end">
                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Publish Post
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
```

Notice a few key things here:
- The form `action` is `/posts` and the `method` is `post`, matching the route we defined.
- The `@csrf` directive is included for security.
- We use `{{ $old['title'] ?? '' }}` to repopulate the form with old input if validation fails.
- We check for and display validation errors using `@if ($errors->has('title'))`.

---

## Step 8: Storing the Post

Finally, let's implement the `store` method in `PostController` to handle the form submission.

```php
// src/Controllers/PostController.php -> store()

use Elementary\Validation\Validator;
use Elementary\Utils\FlashBag;

// ... inside the PostController class

public function store(Request $request): Response
{
    $data = $request->post->only(['title', 'content']);

    $validator = new Validator($data, [
        'title' => ['required', 'minLength:5'],
        'content' => ['required', 'minLength:10'],
    ]);

    if ($validator->fails()) {
        return $this->render('posts/create', [
            'errors' => $validator->errors(),
            'old' => $data,
        ]);
    }

    Post::create([
        'title' => $data['title'],
        'content' => $data['content'],
    ]);

    /** @var FlashBag $flashBag */
    $flashBag = $this->container->get(FlashBag::class);
    $flashBag->add('success', 'Post created successfully!');

    return $this->redirectToRoute('posts.index');
}
```

This method does the following:
1.  Grabs the `title` and `content` from the POST request.
2.  Uses the `Validator` to ensure the fields are not empty and meet a minimum length.
3.  If validation fails, it re-renders the `create` view, passing back the errors and the user's old input.
4.  If validation succeeds, it creates the new post in the database using `Post::create()`.
5.  It adds a "flash message" to the session to be displayed on the next page.
6.  It redirects the user back to the main posts page.

To see the flash message, you can add this snippet to your `templates/posts/index.cigg` file, right above the `<h1>` tag:

```html
@if ($__container->get(\Elementary\Utils\FlashBag::class)->has('success'))
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
        <span class="block sm:inline">{{ $__container->get(\Elementary\Utils\FlashBag::class)->get('success') }}</span>
    </div>
@endif
```

---

## Conclusion

Congratulations! You have successfully built a simple blog using the Elementary framework. 

You have learned how to:
-   Create database tables using console commands.
-   Define Models to interact with your database.
-   Create Controllers to handle requests.
-   Define routes and link them to controller actions.
-   Create views using the Cigg templating engine with layouts and loops.
-   Process form submissions with validation and flash messaging.

From here, you can explore more of the framework's features. Try implementing edit and delete functionality for posts, adding user associations, or exploring the powerful **Spark** reactive component system.

Happy coding!



