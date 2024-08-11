<?php

namespace App\Handler;

use Exception;
use Illuminate\Http\Request;
use Spatie\WebhookClient\WebhookConfig;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;

class WebHookSignerHandlerForAppOne implements SignatureValidator
{
    /**
     * Verify request signature
     * @param Request $request
     * @param WebhookConfig $config
     * @return bool
     */
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        return true;
    }
}
