<?php

namespace App\Services;
use App\Model\Ads;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;
use App\Model\Article;
use App\Model\AdsCategory;

class IndexService extends BaseService{


    public static function getBanner($source){
        $categroy_name_prefix = 'app';
        $category_name = $categroy_name_prefix.'_sy_banner';
        $category = AdsCategory::where('label',$category_name)->first();
        if(!$category){
            return [];
        }

        $search = [
            'category_id'=> $category->id,
            'status' => 1
        ];
        $data = Ads::where($search)->select('id','title','img','url','article_id')->orderBy('sort','desc')->get()->toArray();
        if(!$data){
            return [];
        }

        if ($categroy_name_prefix == 'pc'){
            // 查询文章属于什么类型
            foreach ($data as $k => &$v){
                if ($v['article_id'] > 0){
                    $article = Article::query()->with('category')->where('status', 1)->where('id', $v['article_id'])->first();
                    if (!$article){
                        $v['url'] = '';
                        continue;
                    }
                    if ($article->category->lable == 'issue'){
                        $v['url'] = '/#/information?info_id='.$article->category->id.'&name=faq&article_id='.$v['article_id'];
                    }
                    else{
                        $v['url'] = '/#/information?info_id='.$v['article_id'].'&name=announcement';
                    }
                }else{
                    $v['url'] = '';
                }
            }
        }else{
            foreach ($data as $k => &$v){
                if ($v['article_id'] > 0){
                    $article = Article::query()->with('category')->where('status', 1)->where('id', $v['article_id'])->first();
                    if (!$article){
                        $v['url'] = '';
                        continue;
                    }
                }else{
                    $v['url'] = '';
                }
            }
        }
        //Redis::set('carousel:'.$is_pc,json_encode($data));
        return $data;
    }


    /**
     * 首页
     * @return array|mixed
     */
    public static function getArticle(){
        // notice
        $result = Redis::get('article_shift');
        if($result){
            $result = json_decode($result,true);
            $return = [];
            foreach($result as $item){
                $return[] = [
                    'id' => $item['id'],
                    'title' => $item['title']
                ];
            }
            return $return;
        }
        $search = [
            'status'=> 1,
            'is_shift' => 1,
        ];
        $result = Article::where($search)->select('id','title','content','is_shift','type')
                    ->orderBy('create_time','desc')->get()->toArray();
        Redis::set("article_shift",json_encode($result));
        foreach($result as &$item){
            unset($item['content']);
            unset($item['type']);
        }
        return $result;
    }

    public static function getArticleDetail($id){
        $result = Redis::get('article_shift');
        if($result){
            $result = json_decode($result,true);
            $return['id'] = $id;
            foreach($result as $item){
                if($item['id'] == $id){
                    return $item;
                }
            }
        }

        $result = Article::query()->where(['status' => 1,'id'=>$id,'category_id' => 0])->pluck('content')->first();
        if(!$result){
            static::addError('公告不存在或已被删除',404);
            return false;
        }
        return $result;

    }
}

