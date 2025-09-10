<?php

declare(strict_types=1);

namespace Elementary\Http;

use Elementary\Authentication\UserInterface;
use Elementary\Utils\ParameterBag;
use Elementary\Utils\SessionBag;
use Elementary\Utils\UploadedFile;

final class Request {

    public readonly ParameterBag $get;
    public readonly ParameterBag $post;
    public readonly ParameterBag $cookies;
    public readonly ParameterBag $files;
    public readonly ParameterBag $server;
    public readonly ParameterBag $headers;
    public readonly ParameterBag $attributes;
    public readonly ParameterBag $request;
    public readonly SessionBag $session;
    public ?UserInterface $user = null;
    public readonly ?string $content;


    /**
     * Constructor for the Request class.
     *
     * @param array $get The GET parameters.
     * @param array $post The POST parameters.
     * @param array $cookies The cookies.
     * @param array $files The uploaded files.
     * @param array $server The server parameters.
     * @param array $headers The request headers.
     * @param array $request The request parameters.
     * @param SessionBag $session The session bag.
     * @param array $attributes Additional attributes for the request.
     * @param string|null $content The raw content of the request body, if any.
     */
    public function __construct(
        array $get,
        array $post,
        array $cookies,
        array $files,
        array $server,
        array $headers,
        array $request,
        SessionBag $session,
        array $attributes = [],
        ?string $content = null

    ) {
        $this->get = new ParameterBag($get);
        $this->post = new ParameterBag($post);
        $this->cookies = new ParameterBag($cookies);
        $this->files = new ParameterBag(array_map(array: $files, callback: fn($file) => new UploadedFile(...$file)));
        $this->server = new ParameterBag($server);
        $this->headers = new ParameterBag($headers);
        $this->attributes = new ParameterBag($attributes);
        $this->request = new ParameterBag($request);
        $this->session = $session;
        $this->content = $content;
    }
    public static function createFromGlobals(): self {
        return new self(
            get: $_GET,
            post: $_POST,
            cookies: $_COOKIE,
            files: $_FILES,
            server: $_SERVER,
            headers: getallheaders(),
            request: $_REQUEST,
            session: new SessionBag(),
            attributes: [],
            content: file_get_contents('php://input')
        );
    }

    public function method(): string {
        return $this->server->get('REQUEST_METHOD', 'GET');
    }

    public function uri(): string {
        return $this->server->get('REQUEST_URI', '/');
    }

    public function pathinfo(): string {
        return $this->server->get('PATH_INFO', '/');
    }

    public function isMethod(string $method): bool {
        return strtoupper($this->method()) === strtoupper($method);
    }
}
