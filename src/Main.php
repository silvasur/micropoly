<?php

namespace Micropoly;

use Closure;
use FastRoute\Dispatcher;
use FastRoute\RouteCollector;
use Micropoly\Handlers\ApiTagsHandler;
use Micropoly\Handlers\AttachmentHandler;
use Micropoly\Handlers\Index;
use Micropoly\Handlers\MethodNotAllowedHandler;
use Micropoly\Handlers\NewNote;
use Micropoly\Handlers\NoteHandler;
use Micropoly\Handlers\NotFoundHandler;

use Micropoly\Handlers\Search;
use Micropoly\Models\Attachment;
use function FastRoute\simpleDispatcher;

class Main implements Entrypoint
{
    private static function buildRoutes(RouteCollector $r)
    {
        $r->addRoute(["GET"], "/", Index::class);
        $r->addRoute(["GET", "POST"], "/new-note", NewNote::class);
        $r->addRoute(["GET"], "/search", Search::class);
        $r->addRoute(["GET", "POST"], "/n/{id}", NoteHandler::class);
        $r->addRoute(["GET"], "/api/tags", ApiTagsHandler::class);
        $r->addRoute(["GET"], "/attachments/{id}", AttachmentHandler::class);
    }

    public function run(Env $env)
    {
        $disp = simpleDispatcher(Closure::fromCallable([self::class, "buildRoutes"]));

        $uri = preg_replace('/\?.*$/', "", $_SERVER["REQUEST_URI"]);
        $result = $disp->dispatch($_SERVER["REQUEST_METHOD"], $uri);
        switch ($result[0]) {
            case Dispatcher::NOT_FOUND:
                $handlerCls = NotFoundHandler::class;
                $vars = [];
                break;
            case Dispatcher::FOUND:
                [, $handlerCls, $vars] = $result;
                break;
            case Dispatcher::METHOD_NOT_ALLOWED:
                $handlerCls = MethodNotAllowedHandler::class;
                $vars = ["allowed" => $result[1]];
                break;
            default:
                throw new \DomainException("Unexpected routing result: {$result[0]}");
        }

        $handler = new $handlerCls();
        if (!($handler instanceof Handler)) {
            throw new \DomainException("handler is not an instance of ".Handler::class);
        }

        $handler->handle($env, $vars);
    }
}
