<?php

namespace App\Middleware;

use Hyperf\Contract\TranslatorInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Hyperf\Di\Annotation\Inject;

class LangMiddleware implements MiddlewareInterface
{
    /**
     * @Inject
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @inheritDoc
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeader('Accept-Language');
        $lang = $header ? (in_array($header[0], ['zh-cn', 'zh_CN']) ? 'zh_CN' : 'en') : 'zh_CN';
        $this->translator->setLocale($lang);

        return $handler->handle($request);
    }
}