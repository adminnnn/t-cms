<?php

namespace App\Entities;


use App\Entities\Traits\Listable;
use Cache;
use Carbon\Carbon;
use Config;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use Ty666\PictureManager\Traits\Picture;
use PictureManager;

class Post extends BaseModel
{
    use SoftDeletes, Picture, Listable;

    protected $fillable = ['title', 'user_id','author_info', 'excerpt', 'type', 'views_count', 'cover', 'status' , 'template', 'top', 'published_at'];
    protected $dates = ['deleted_at', 'top', 'published_at'];

    protected static $allowSearchFields = ['title', 'author_info', 'excerpt'];
    protected static $allowSortFields = ['title', 'status', 'views_count', 'top', 'order', 'published_at'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function categories()
    {
        return $this->belongsToMany(Category::class)->ordered()->recent();
    }

    public function scopePost($query)
    {
        return $query->where('type', 'post');
    }
    public function scopeApplyFilter($query, $data)
    {
        $data = $data->only('q', 'type', 'status', 'orders');
        $query->orderByTop();
        if(!is_null($data['q']))
        {
            $query->withSimpleSearch($data['q']);
        }
        if(!is_null($data['orders']))
        {
            $query->withSort($data['orders']);
        }
        switch ($data['type']){
            case 'page':
                $query->type();
                break;
            case 'draft':
                $query->draft();
        }
        switch ($data['status']){
            case 'publish':
                $query->publish();
                break;
        }
        return $query->ordered()->recent();
    }

    public function scopePage($query)
    {
        return $query->where('type', 'page');
    }

    public function scopeDraft($query)
    {
        return $query->where('type', 'draft');
    }

    public function scopePublish($query)
    {
        return $query->where('status', 'publish');
    }

    public function scopeOrderByTop($query)
    {
        return $query->orderBy('top', 'DESC');
    }

    public function getViewsCountAttribute($viewsCount)
    {
        return intval(Cache::rememberForever('post_views_count_' . $this->id, function () use ($viewsCount) {
            return $viewsCount;
        }));
    }

    public function addViewCount()
    {
        //todo 感觉这里并发会有问题 2017年3月27日23:18:41
        $cacheKey = 'post_views_count_' . $this->id;
        if (Cache::has($cacheKey)) {
            $currentViewCount = Cache::increment($cacheKey);
            if ($currentViewCount - $this->views_count >= Config::get('cache.post.cache_views_count_num')) {
                //将阅读量写入数据库
                //User::where($this->getKeyName(), $this->getKey())->increment('views_count', config('cache.post.cache_views_count_num'));
                User::where($this->getKeyName(), $this->getKey())->update('views_count', $currentViewCount);
            }
        } else {
            Cache::forever($cacheKey, $this->views_count + 1);
        }
    }

    public static function movePosts2Categories($categoryIds, $postIds)
    {
        $categoryIds = Category::findOrFail($categoryIds)->pluck('id');
        $posts = static::findOrFail($postIds);
        $posts->each(function ($post) use ($categoryIds){
            $post->categories()->sync($categoryIds);
        });
    }

    public function content()
    {
        return $this->hasOne(PostContent::class);
    }

    public function saveCategories($categories)
    {
        if($categories instanceof Collection)
        {
            $categories = $categories->pluck('id');
        }else if(is_string($categories)){
            $categories = explode(',', $categories);
        }
        $this->categories()->sync($categories);
    }

    public function getCoverUrlsAttribute()
    {
        //todo 换一个默认封面
        return $this->getPicure($this->cover, ['sm', 'md', 'lg', 'o'], asset('images/default_avatar.jpg'));
    }

    public static function createPost($data)
    {
        $data['type'] = 'post';
        // 处理置顶
        if(isset($data['top'])){
            $data['top'] = Carbon::now();
        }
        if(isset($data['cover_in_content'])){
            //todo size
            $data['conver'] = PictureManager::convert(public_path($data['cover_in_content']), 200, 300);
        }
        $data['published_at'] = Carbon::createFromTimestamp(strtotime($data['published_at']));

        $post = static::create($data);
        if(isset($data['content'])){
            $post->content()->create([
                'content' => clean($data['content'])
            ]);
        }

        // 处理分类
        if(!empty($data['category_ids'])){
            $post->saveCategories($data['category_ids']);
        }

        return $post;
    }
}
