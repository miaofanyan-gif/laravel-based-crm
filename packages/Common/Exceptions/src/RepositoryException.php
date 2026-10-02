<?php

/*
 * This file is part of jwt-auth.
 *
 * (c) Sean Tymon <tymon148@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Common\Exceptions;

use Exception;

use Common\Bootstrap\Traits\ResponseTrait;
class RepositoryException extends Exception
{
    use ResponseTrait;
    /**
     * {@inheritdoc}
     */
    protected $message = 'An error occurred';

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request)

    {
        return $this->ResponseFail($this->getCode(), $this->getMessage());

    }

}
