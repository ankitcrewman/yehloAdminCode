<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CallbackUrl;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use App\Models\PaymentRequest;


class Webhook extends Controller
{
    /**
     * Handle incoming webhook requests.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function handle(Request $request)
    {
        Log::info('This message will go to the daily log file.');
        Log::channel('webhook')->info('This message will be logged to the webhook file.');

        $requestData = $request->all();
        $base64Response = $requestData['response'] ?? null;

        // Create a new CallbackUrl instance and save the request data
        $callbackUrl = new CallbackUrl();
        $callbackUrl->payload = json_encode([
            'url' => $request->input('response'),
            'event_type' => $request->input('event_type'),
            'data' => $request->except(['url', 'event_type'])
        ]); // Store the request data in the payload as JSON
        $callbackUrl->save();

        // Find the saved CallbackUrl instance
        $savedCallbackUrl = CallbackUrl::find($callbackUrl->id);
        $decryptedPayload = json_decode($savedCallbackUrl->payload);

        // Decode the base64 encoded response
        $decodedUrl = base64_decode($decryptedPayload->url);
        $decodedData = json_decode($decodedUrl);

        // Retrieve the merchant transaction ID
        $responseTransaction = $decodedData->data->merchantTransactionId ?? null;

        if ($responseTransaction) {
            // Retrieve the corresponding PaymentRequest
            $transactionRequest = PaymentRequest::where('merchant_transaction_id', $responseTransaction)->first();

            if ($transactionRequest) {
                // Check if the transaction was successful
                if (isset($decodedData->success) && $decodedData->success === true) {
                    if (isset($transactionRequest->success_hook) && function_exists($transactionRequest->success_hook)) {
                        call_user_func($transactionRequest->success_hook, $transactionRequest);
                        $transactionRequest->is_paid = 1;

                        // Save the updated transaction request
                        $transactionRequest->save();
                        return response()->json(['message' => 'Webhook processed successfully'], 200);
                    }
                } else {
                    if (isset($transactionRequest->failure_hook) && function_exists($transactionRequest->failure_hook)) {
                        call_user_func($transactionRequest->failure_hook, $transactionRequest);
                        Log::channel('webhook')->info('Webhook processed with failure');
                        return response()->json(['message' => 'Webhook processed with failure'], 200);
                    }
                }
            } else {
                // Log or handle the case where the transaction request wasn't found
                Log::warning('Transaction request not found for merchantTransactionId: ' . $responseTransaction);
                return response()->json(['message' => 'Transaction request not found'], 404);
            }
        } else {
            // Log or handle the case where merchantTransactionId is missing
            Log::warning('Missing merchantTransactionId in decoded response');
            return response()->json(['message' => 'Invalid webhook payload'], 400);
        }
    }

    public function order_sdk(Request $request)
    {

        $requestData = $request->all();
        $base64Response = $requestData['response'] ?? null;

        // Create a new CallbackUrl instance and save the request data
        $callbackUrl = new CallbackUrl();
        $callbackUrl->payload = json_encode([
            'url' => $request->input('response'),
            'event_type' => $request->input('event_type'),
            'data' => $request->except(['url', 'event_type'])
        ]); // Store the request data in the payload as JSON
        $callbackUrl->save();

        // Find the saved CallbackUrl instance
        $savedCallbackUrl = CallbackUrl::find($callbackUrl->id);
        $decryptedPayload = json_decode($savedCallbackUrl->payload);

        // Decode the base64 encoded response
        $decodedUrl = base64_decode($decryptedPayload->url);
        $decodedData = json_decode($decodedUrl);

        // Retrieve the merchant transaction ID
        $responseTransaction = $decodedData->data->merchantTransactionId ?? null;


        if ($responseTransaction) {
            // Retrieve the corresponding PaymentRequest
            $transactionRequest = PaymentRequest::where('merchant_transaction_id', $responseTransaction)->first();

            if ($transactionRequest) {
                // Check if the transaction was successful
                if (isset($decodedData->success) && $decodedData->success === true) {
                    if (isset($transactionRequest->success_hook) && function_exists($transactionRequest->success_hook)) {
                        call_user_func($transactionRequest->success_hook, $transactionRequest);
                        $transactionRequest->is_paid = 1;

                        // Save the updated transaction request
                        $transactionRequest->save();
                        return response()->json(['message' => 'Webhook processed successfully'], 200);
                    }
                } else {
                    if (isset($transactionRequest->failure_hook) && function_exists($transactionRequest->failure_hook)) {
                        call_user_func($transactionRequest->failure_hook, $transactionRequest);
                        Log::channel('webhook')->info('Webhook processed with failure');
                        return response()->json(['message' => 'Webhook processed with failure'], 200);
                    }
                }
            } else {
                // Log or handle the case where the transaction request wasn't found
                Log::warning('Transaction request not found for merchantTransactionId: ' . $responseTransaction);
                return response()->json(['message' => 'Transaction request not found'], 404);
            }
        } else {
            // Log or handle the case where merchantTransactionId is missing
            Log::warning('Missing merchantTransactionId in decoded response');
            return response()->json(['message' => 'Invalid webhook payload'], 400);
        }
    }
}
