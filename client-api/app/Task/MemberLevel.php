<?php
namespace App\Task;

use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Crontab\Annotation\Crontab;
use Hyperf\Di\Annotation\Inject;
use Hyperf\DbConnection\Db;
use App\Exception\DbException;
class MemberLevel
{
	protected $description = '跑用户推荐关系';

    public function handle(){
        \App\Model\User::query()->where('is_level',0)->where('id','>=',1000000)->orderBy('id','asc')->chunk(10,function($list){
            foreach($list as $v){
                $data=[];
                $parents=\App\Model\MemberLevel::where('user_id',$v['from_uid'])->get();
                foreach($parents as $val){
                    $data[]=['user_id'=>$v['id'],'pid'=>$val['pid'],'from_uid'=>$v['from_uid'],'level'=>$val['level']+1];
                }
                $data[]=['user_id'=>$v['id'], 'pid'=>$v['from_uid'],'from_uid'=>$v['from_uid'],'level'=>0];
                Db::beginTransaction();
                try{
                    if(!empty($data)){
                        $memberLevel=new \App\Model\MemberLevel;
                        $res=$memberLevel->insert($data);
                        if($res){
                            $v->is_level=1;
                            $ret=$v->save();
                            if(!$ret){
                               throw new DbException('更新用户'.$v['id'].'推荐关系失败');
                            }
                        }
                    }
                    Db::commit();
                    \App\Utils\Log::info('用户id'.$v['id'].'推荐关系更新完成');
                } catch(\Throwable $ex){
                    Db::rollBack();
                    \App\Utils\Log::info('用户id'.$v['id'].'推荐关系更新'.$ex->getMessage());
                }
            }
        });
    }
}