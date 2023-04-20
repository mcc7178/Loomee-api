<?php

namespace App\Controller;

use App\Constants\StatusCode;
use App\Model\NoticeLog;
use App\Service\Auth\UserService;
use Hyperf\HttpServer\Contract\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class NoticeController extends AbstractController
{
    /**
     * 列表
     * @param RequestInterface $request
     * @return ResponseInterface
     */
    public function list(RequestInterface $request)
    {
        $user = UserService::getInstance()->getUserInfoByToken();
        $page = $request->input('page', 1);
        $size = $request->input('size', 20);
        $type = $request->input('type', '');
        $chain_id = $request->input('chain_id', '');
        $list = NoticeLog::getList($user->id, $type, $chain_id, $page, $size);
        return $this->success($list);
    }

    /**
     * 详情
     * @param int $id
     * @return ResponseInterface
     */
    public function info(int $id)
    {
        $info = NoticeLog::query()->findOrFail($id);
        $user = UserService::getInstance()->getUserInfoByToken();
        if ($info->user_id != $user->id) {
            $this->throwExp(400);
        }
        if ($info->status == 0) {
            $info->status = 1;
            $res = $info->save();
            if (!$res) {
                $this->throwExp(400);
            }
        }
        return $this->success([
            'list' => $info->toArray()
        ]);
    }

    /**
     * @return ResponseInterface
     */
    public function read()
    {
        $chain_id = $this->request->input('chain_id', '');
        if (!$chain_id) {
            $this->throwExp(StatusCode::ERR_PARAMETER_ERROR);
        }
        $user = UserService::getInstance()->getUserInfoByToken();
        NoticeLog::query()
            ->where('user_id', $user->id)
            ->where('chain_id', $chain_id)
            ->update(['status' => 1, 'updated_at' => date('Y-m-d H:i:s')]);

        return $this->successByMessage();
    }
}