<?php

namespace Taushifk\Razorpay\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Taushifk\Razorpay\Cashier;

/**
 * @see https://developer.paddle.com/webhook-reference/verifying-webhooks
 */
class VerifyWebhookSignature
{
    const SIGNATURE_KEY = 'X_RAZORPAY_SIGNATURE';

    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return \Illuminate\Http\Response
     *
     * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
     */
    public function handle(Request $request, Closure $next)
    {
        $signature = (string) $request->header(self::SIGNATURE_KEY);
        
        if ($this->isInvalidSignature($request->getContent(), $signature)) {
            throw new AccessDeniedHttpException('Invalid webhook signature.'.$signature);
        }
        
        return $next($request);
    }

    /**
     * Validate signature.
     *
     * @param  array  $fields
     * @param  string  $signature
     * @return bool
     */
    protected function isInvalidSignature($fields, $signature)
    {
        try {
            Cashier::razorpay()->utility->verifyWebhookSignature($fields, $signature, config('cashier.webhook_secret'));
        } catch(\Exception $e) {
            return true;
        }
        
        return false;
    }
}
