<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Utils\PayOSHandler;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use App\Utils\Sapo;
use Exception;

class WebhookTransaction
{
  public function __invoke(Request $request, Response $response, array $args): Response
  {
    $sapo = new Sapo();
    $payOS = (new PayOSHandler())->PayOS();

    try {
      $contentType = $request->getHeaderLine('Content-Type');
      if (!strstr($contentType, 'application/json')) {
        return $response->withStatus(400);
      }
      $contents = json_decode(file_get_contents('php://input'), true);
      if (json_last_error() !== JSON_ERROR_NONE) {
        return $response->withStatus(400);
      }

      $request = $request->withParsedBody($contents);
      $body = $request->getParsedBody();
      if (!$payOS->verifyPaymentWebhookData($body)) {
        return $response->withStatus(400);
      }
      // check demo data when confirm hook
      if ($body['data']['accountNumber'] === '12345678' && $body['data']['reference'] === 'TF230204212323') {
        return $response;
      }
      $orderCode = $body['data']['orderCode'];
      $sapoStatusOrder = $sapo->getOrderById($orderCode);
      if (!$sapoStatusOrder || !isset($sapoStatusOrder['order'])) {
        $response->getBody()->write('NOT FOUND ORDER');
        return $response->withStatus(400);
      }
      if ($sapoStatusOrder['order']['financial_status'] === HARAVAN_ORDER_PAID_MESSAGE) {
        return $response;
      }
      $sapo->confirmOrder($orderCode);
      return $response;
    } catch (Exception $e) {
      error_log(var_export($e->getMessage(), true));
      $statusCode = $e->getCode() ?: 500;
      $response->getBody()->write(json_encode(['error' => $e->getMessage()]));
      return $response->withStatus($statusCode);
    }
  }
}