<?php


namespace App\Services;


use App\Model\Article;
use App\Model\ArticleCategory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;

class ArticleService extends BaseService
{
    /**
     * 文章标题列表
     * @param $label
     * @return \Illuminate\Database\Eloquent\Builder[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function getArticleList($label, $is_shift = 0, $search = [], $lang)
    {
//        $category = ArticleCategory::query()
//            ->where('type', $label)
//            ->select('id')
//            ->first();
//        if (!$category) {
//            return [];
//        }
        $list = Article::query()
            ->select('id', "title_" . $lang . " as title", "content_" . $lang .  " as content", 'create_time')
            ->where('status', 1)
            ->when($is_shift == 1, function ($query) use ($is_shift) {
                return $query->where('is_shift', $is_shift);
            })
            ->where($search)
            ->where('category_id', $label)
            ->simplePaginate();
        return $list;
    }

    public static function getArticleListOld($label, $is_shift = 0, $search = [])
    {
        $category = ArticleCategory::query()
            ->where('type', $label)
            ->select('id')
            ->first();
        if (!$category) {
            return [];
        }
        $list = Article::query()
            ->select(['id', 'title', 'content'])
            ->where('status', 1)
            ->when($is_shift == 1, function ($query) use ($is_shift) {
                return $query->where('is_shift', $is_shift);
            })
            ->where($search)
            ->where('category_id', $category->id)
            ->get();
        return $list;
    }

    /**
     * 文章详情
     * @param $where
     * @param $field
     * @return \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model|object|null
     */
    public static function getArticleDetail($where, $field)
    {
//        $result = Article::query()->where(['status' => 1])->where($where)->first();
        $result = Article::query()->where(['status' => 1])->where($where)->select('content_' . "$field as content", 'title_'."$field as title",'create_time')->first();
        return $result;
    }

    public static function allProblem()
    {
        
    }
}