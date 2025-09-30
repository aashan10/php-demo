<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Post;
use Elementary\Http\Controller;
use Elementary\Http\Request;
use Elementary\Http\Response;
use Elementary\Validation\Validator;
use Elementary\Utils\FlashBag;

class PostController extends Controller 
{
    /**
     * Display a listing of the posts.
     */
    public function index(): Response
    {
        $posts = Post::all();

        return $this->render('posts/index', ['posts' => $posts]);
    }

    /**
     * Display the specified post.
     */
    public function show(Request $request): Response
    {
        $id = $request->attributes->get('id');
        $post = Post::find((int)$id);

        if (!$post) {
            return $this->render('errors/404', []);
        }

        return $this->render('posts/show', ['post' => $post]);
    }

    /**
     * Show the form for creating a new post.
     */
    public function create(): Response
    {
        return $this->render('admin/posts/create');
    }

    /**
     * Store a newly created post in the database.
     */
    public function store(Request $request): Response
    {
        $data = $request->post->only(['title', 'content']);

        $validator = new Validator($data, [
            'title' => ['required', 'minLength:5'],
            'content' => ['required', 'minLength:10'],
        ]);

        if ($validator->fails()) {
            return $this->render('admin/posts/create', [
                'errors' => $validator->errors(),
                'old' => $data,
            ]);
        }

        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $data['title']), '-'));
        
        Post::create([
            'title' => $data['title'],
            'content' => $data['content'],
            'excerpt' => substr($data['content'], 0, 200),
            'slug' => $slug,
            'status' => 'published',
            'user_id' => $request->user->id,
        ]);

        $this->addFlash('success', 'Post created successfully!');

        return $this->redirectToRoute('posts.index');
    }
}
