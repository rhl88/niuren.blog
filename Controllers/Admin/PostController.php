<?php

namespace App\Apps\NiurenBlog\Controllers\Admin;

use App\Apps\NiurenBlog\Models\Post;
use Illuminate\Http\Request;

class PostController
{
    public function index(Request $request)
    {
        return view('niuren.blog::Admin.Post.index');
    }

    public function create()
    {
        return view('niuren.blog::Admin.Post.create');
    }

    public function edit($id)
    {
        $post = Post::findOrFail($id);
        return view('niuren.blog::Admin.Post.create', compact('post'));
    }
}
