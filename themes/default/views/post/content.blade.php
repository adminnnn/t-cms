@extends('layouts.content')
@section('content')
    <!--<div class="content_header">
        <a href="#" class="title">t-cms</a>
    </div>-->
    <div class="container">
        <h1 class="content_title">{!! $post->title !!}</h1>
        <p class="info">
            <span>{!! $post->views_count !!} 次阅读</span>
            <span>{!! $post->published_at !!}</span>
        </p>
        <div class="content_main">
            <div class="content">
                {!! $post->content->content !!}
            </div>
            @include('post.particals.content_side', ['thisPost'=>$post])
        </div>
    </div>
    @inject('navigation', 'App\T\Navigation\Navigation')
    @php
        $nextPost = $post->getNextPost($navigation->getActiveNav());
    @endphp
    @if($nextPost)
    <div class="contnet_footer">
        <div class="container">
            <a href="{!! $nextPost->present()->getUrl() !!}">
                <div class="nav-title">
                    <span>阅读下一篇</span>
                </div>
                <h1 class="content_title">{!! $nextPost->title !!}</h1>
                <p class="info">
                    <span>{!! $nextPost->views_count !!}次阅读</span>
                    <span>{!! $nextPost->published_at !!}</span>
                </p>
            </a>
        </div>
    </div>
    @endif
@endsection