<?php

declare(strict_types=1);

namespace App\Controller\Common;

use App\Middleware\RequestMiddleware;
use App\Controller\AbstractController;
use App\Service\Common\UploadService;
use Hyperf\HttpServer\Annotation\Controller;
use Hyperf\HttpServer\Annotation\Middleware;
use Hyperf\HttpServer\Annotation\Middlewares;
use Hyperf\HttpServer\Annotation\RequestMapping;


class UploadController extends AbstractController
{

    /**上传单张图片接口
     * Notes:
     * User: Deycecep
     * DateTime: 2022/4/15 14:34
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \App\Exception\Handler\BusinessException
     */
    public function uploadSinglePic()
    {
        $params = [
            'is_avatar' => $this->request->input('is_avatar',0),
            'savePath' => 'pic',//$this->request->input('savePath'),
            'file' => $this->request->file('file'),
        ];
        //配置验证
        $rules = [
            'savePath' => 'required',
            'file' => 'required |file|image',
        ];
        $message = [
            'savePath.required' => '[savePath] missing',
            'file.required' => '[file] missing',
            'file.file' => '[file] Argument must be file type',
            'file.image' => '[file] The file must be an image（jpeg、png、bmp、gif、svg）',
        ];
        $this->verifyParams($params, $rules, $message);

        $uploadResult = UploadService::getInstance()->uploadSinglePic($this->request->file('file'), $params['savePath'],$params['is_avatar']);

        return $this->success($uploadResult, __('validation.uploaded_successfully'));
    }

    /**
     * 上传单张图片接口
     * @RequestMapping(path="single_pic_by_base64", methods="post")
     * @Middlewares({
     *     @Middleware(RequestMiddleware::class),
     * })
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \League\Flysystem\FileExistsException
     */
    public function uploadSinglePicByBase64()
    {
        $params = [
            'savePath' => $this->request->input('savePath'),
            'file' => $this->request->input('file'),
        ];
        //配置验证
        $rules = [
            'savePath' => 'required',
            'file' => 'required ',
        ];
        $message = [
            'savePath.required' => '[savePath]缺失',
            'file.required' => '[file]缺失',
        ];
        $this->verifyParams($params, $rules, $message);

        base64DecImg($params['file']);
        $uploadResult = UploadService::getInstance()->uploadSinglePicByBase64($params['file'], $params['savePath']);
        return $this->success($uploadResult);
    }

    /**
     * 上传单张图片根据Blob文件类型
     * @RequestMapping(path="single_pic_by_blob", methods="post")
     * @Middlewares({
     *     @Middleware(RequestMiddleware::class),
     * })
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \League\Flysystem\FileExistsException
     */
    public function uploadSinglePicByBlob()
    {
        $params = [
            'savePath' => $this->request->input('save_path'),
            'file' => $this->request->file('file'),
        ];
        //配置验证
        $rules = [
            'savePath' => 'required',
            'file' => 'required|file',
        ];
        $message = [
            'savePath.required' => '[savePath]缺失',
            'file.required' => '[file]缺失',
            'file.file' => '[file] 参数必须为文件类型',
        ];
        $this->verifyParams($params, $rules, $message);

        $uploadResult = UploadService::getInstance()->uploadSinglePicByBlob($params['file'], $params['savePath']);
        return $this->success($uploadResult);
    }
}