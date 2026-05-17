<?php

declare(strict_types=1);

use WHMCS\Database\Capsule;

if (!defined('WHMCS')) {
    require_once __DIR__ . '/../../../../init.php';
}

require_once __DIR__ . '/domain_hub.php';

if (!class_exists('CfTelegramGroupRewardService')) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(503);
    echo json_encode(['ok' => false, 'error' => 'service_unavailable']);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET' && trim((string) ($_GET['debug'] ?? '')) === '1') {
    $settings = function_exists('cf_get_module_settings_cached') ? cf_get_module_settings_cached() : [];
    if (!is_array($settings)) {
        $settings = [];
    }
    $token = CfTelegramGroupRewardService::resolveBotToken($settings);
    $expectedWebhookUrl = CfTelegramGroupRewardService::getExpectedWebhookUrl();
    $webhookInfo = CfTelegramGroupRewardService::getWebhookDebugInfo($settings);
    $setWebhookResult = null;
    if (trim((string) ($_GET['set_webhook'] ?? '')) === '1') {
        $setWebhookResult = CfTelegramGroupRewardService::setWebhookToExpected($settings);
        $webhookInfo = CfTelegramGroupRewardService::getWebhookDebugInfo($settings);
    }
    $currentWebhookUrl = trim((string) ($webhookInfo['url'] ?? ''));
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'ok' => true,
        'debug' => true,
        'feature_enabled' => CfTelegramGroupRewardService::isEnabled($settings),
        'bot_username' => CfTelegramGroupRewardService::resolveBotUsername($settings),
        'bot_token_valid' => preg_match('/^[0-9]{5,20}:[A-Za-z0-9_-]{20,120}$/', $token) === 1,
        'webhook_expected' => $expectedWebhookUrl !== '' ? $expectedWebhookUrl : 'https://你的WHMCS域名/modules/addons/domain_hub/telegram_webhook.php',
        'webhook_url_empty' => $currentWebhookUrl === '',
        'webhook_url_matches_expected' => $expectedWebhookUrl !== '' && $currentWebhookUrl === $expectedWebhookUrl,
        'webhook_info' => $webhookInfo,
        'set_webhook_result' => $setWebhookResult,
    ]);
    exit;
}

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'method_not_allowed']);
    exit;
}

$settings = function_exists('cf_get_module_settings_cached') ? cf_get_module_settings_cached() : [];
if (!is_array($settings)) {
    $settings = [];
}
$raw = file_get_contents('php://input');
$update = json_decode((string) $raw, true);
if (!is_array($update)) {
    $update = [];
}

try {
    $result = CfTelegramGroupRewardService::handleBotBindWebhook($settings, $update);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($result);
} catch (CfTelegramGroupRewardException $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getReason()]);
} catch (\Throwable $e) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'internal_error']);
}
exit;
