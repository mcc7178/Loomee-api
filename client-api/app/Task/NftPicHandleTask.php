<?php

namespace App\Task;

use App\Foundation\Facades\Log;
use App\Model\Product\Product;
use Hyperf\DbConnection\Db;
use Swoole\Runtime;

class NftPicHandleTask
{
    public function handle()
    {
        Db::enableQueryLog();
        $data = Product::query()
            ->select(['picture', 'animation_url', 'id'])
            ->where("picture", 'like', '%/%')
            ->orWhere('animation_url', 'like', '%/%')
            ->get()
            ->toArray();
        if ($data) {
            Runtime::enableCoroutine();
            Log::codeDebug()->info(__METHOD__ . "处理数据,数量:" . count($data));
            foreach ($data as $item) {
                $id = $item['id'];
                $picture = $item['picture'];
                $animation_url = $item['animation_url'];
                go(function () use ($id, $picture) {
                    $this->handlePic($id, $picture);
                });
                if ($item['animation_url']) {
                    go(function () use ($id, $animation_url) {
                        $this->handleAnimation($id, $animation_url);
                    });
                }
            }
        }
    }

    public function handlePic($id, $picture)
    {
        if ($picture) {
            $file = file_get_contents($picture);
            $pathinfo = pathinfo($picture);
            $dir = date('YmdHis') . str_pad($id, 8, '0', STR_PAD_LEFT) . '.' . $pathinfo['extension'];
            $path = './pic/' . $dir;

            $res = file_put_contents($path, $file);
            if ($res !== false) {
                $product = Product::query()->find($id);
                if (file_exists('./pic/' . $product->picture)) {
                    @unlink('./pic/' . $product->picture);
                }
                Log::channel('code_debug')->info('删除原始图片...' . $product->picture);
                Log::channel('code_debug')->info('新增图片.......' . $dir);
                $product->picture = $dir;
                $product->save();
                Log::channel('code_debug')->info("图片处理完成...$id");
            }
        }
        return true;
    }

    public function handleAnimation($id, $animation)
    {
        if ($animation) {
            $file = file_get_contents($animation);
            $pathinfo = pathinfo($animation);
            $dir = date('YmdHis') . str_pad($id, 8, '0', STR_PAD_LEFT) . '.' . $pathinfo['extension'];
            $path = './pic/' . $dir;
            $res = file_put_contents($path, $file);
            if ($res !== false) {
                $product = Product::query()->find($id);
                if (file_exists('./pic/' . $product->animation_url)) {
                    @unlink('./pic/' . $product->animation_url);
                }
                Log::channel('code_debug')->info('删除原始图片...' . $product->animation_url);
                $product->animation_url = $dir;
                $product->save();
                Log::channel('code_debug')->info("视频处理完成...:$id");
            }
        }
        return true;
    }
}