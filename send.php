<?php

mb_internal_encoding('UTF-8');

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  header('Content-Type: text/html; charset=utf-8');
  echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Метод не поддерживается</title><body><h1>405 Метод не поддерживается</h1><p>Используйте POST-запрос для отправки формы.</p></body></html>';
  exit;
}

// ============================================
// EMAIL CONFIGURATION
// ============================================
$to_email = 'info@iqt-group.ru';     // Where to send form submissions
$from_email = 'no-reply@iqt-group.ru';            // Send from your domain email
$from_name = 'IQT Group';
// ============================================

$serviceName = isset($_POST['service_name']) ? trim((string)$_POST['service_name']) : '';

function sanitize_value($value) {
  if (is_array($value)) {
    return array_map('sanitize_value', $value);
  }
  $value = trim((string)$value);
  $value = strip_tags($value);
  if (mb_strlen($value) > 2000) {
    $value = mb_substr($value, 0, 2000) . '…';
  }
  return $value;
}

// Sanitize all input
$clean = sanitize_value($_POST);

// Try to detect reply-to email if present
$replyTo = null;
if (!empty($clean['email']) && filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
  $replyTo = $clean['email'];
}

// Build message
$lines = [];

// Add service name
if (!empty($serviceName)) {
  $lines[] = 'Услуга: ' . $serviceName;
}

// Add name
if (!empty($clean['name'])) {
  $lines[] = 'Имя: ' . $clean['name'];
} elseif (!empty($clean['full_name'])) {
  $lines[] = 'Имя: ' . $clean['full_name'];
}

// Add position/role
if (!empty($clean['position'])) {
  $lines[] = 'Должность: ' . $clean['position'];
}

// Add phone number
if (!empty($clean['phone'])) {
  $lines[] = 'Тел. номер: ' . $clean['phone'];
} elseif (!empty($clean['tel'])) {
  $lines[] = 'Тел. номер: ' . $clean['tel'];
}

// Add preferred contact method
if (!empty($clean['contact_method'])) {
  $methodMap = [
    'phone' => 'Телефон',
    'telegram' => 'Telegram',
    'whatsapp' => 'WhatsApp'
  ];
  $methodLabel = $methodMap[$clean['contact_method']] ?? $clean['contact_method'];
  $lines[] = 'Предпочтительный способ связи: ' . $methodLabel;
}

// Add datetime if present (from callback form)
if (!empty($clean['datetime'])) {
  $lines[] = 'Желаемое время звонка: ' . $clean['datetime'];
}

// Add problem description
if (!empty($clean['problem'])) {
  $lines[] = '';
  $lines[] = 'Описание проблемы:';
  $lines[] = $clean['problem'];
}

$message = implode("\n", $lines);

$subjectBase = 'Новая заявка с сайта IQT Group';
if (!empty($serviceName)) {
  $subjectBase .= ' — ' . $serviceName;
}

// Build headers
$headers = [];
$headers[] = 'MIME-Version: 1.0';
$headers[] = 'Content-Type: text/plain; charset=UTF-8';
$headers[] = 'Content-Transfer-Encoding: 8bit';
$headers[] = 'From: ' . $from_name . ' <' . $from_email . '>';
$headers[] = 'X-Mailer: PHP/' . phpversion();

if ($replyTo) {
  $headers[] = 'Reply-To: ' . $replyTo;
}

$headersStr = implode("\r\n", $headers);

// Use PHP's mail() function
$sent = @mail($to_email, $subjectBase, $message, $headersStr, '-f' . $from_email);

// Check if request is AJAX
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($sent) {
  // Success
  $clientName = '';
  if (!empty($clean['name'])) {
    $clientName = $clean['name'];
  } elseif (!empty($clean['full_name'])) {
    $clientName = $clean['full_name'];
  }
  
  if ($isAjax) {
    // Return JSON for AJAX requests
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(200);
    echo json_encode([
      'success' => true,
      'message' => 'Спасибо' . ($clientName ? ', ' . $clientName : '') . '! Ваша заявка отправлена. Мы свяжемся с вами в ближайшее время.',
      'name' => $clientName
    ]);
  } else {
    // Redirect for regular form submissions
    $redirectUrl = './success.html';
    if ($clientName) {
      $redirectUrl .= '?name=' . urlencode($clientName);
    }
    header('Location: ' . $redirectUrl);
    exit;
  }
} else {
  // Error
  if ($isAjax) {
    // Return JSON error for AJAX requests
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(500);
    echo json_encode([
      'success' => false,
      'message' => 'Не удалось отправить сообщение. Попробуйте позже или позвоните нам.'
    ]);
  } else {
    // Show error page for regular form submissions
    http_response_code(500);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="ru"><meta charset="utf-8"><title>Ошибка отправки</title><body><h1>Ошибка отправки</h1><p>Не удалось отправить сообщение. Попробуйте позже или позвоните нам.</p><a href="/">Вернуться на главную</a></body></html>';
    exit;
  }
}
