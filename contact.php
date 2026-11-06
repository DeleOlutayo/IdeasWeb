<?php
// Emails contact form submissions to the IdeasClique inbox.

const RECIPIENT = 'info@ideasclique.com';
const SENDER = 'noreply@ideasclique.com';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(int $status, bool $ok): void {
    http_response_code($status);
    echo json_encode(['ok' => $ok]);
    exit;
}

// Collapses control characters so a field can't add email headers or fake lines.
function field(string $name, int $maxLength): string {
    $value = $_POST[$name] ?? '';
    if (!is_string($value)) {
        return '';
    }
    return trim(mb_substr(preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '', 0, $maxLength, 'UTF-8'));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, false);
}

// Real visitors never see this field, so a value means a bot: report success and send nothing.
if (($_POST['website'] ?? '') !== '') {
    respond(200, true);
}

$name = field('name', 200);
$email = field('email', 254);
$company = field('company', 200);
$service = field('service', 200);

// The message keeps its line breaks.
$rawMessage = $_POST['message'] ?? '';
$message = is_string($rawMessage) ? trim(mb_substr($rawMessage, 0, 5000, 'UTF-8')) : '';

if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, false);
}

$body = "New enquiry from the IdeasClique website\n\n"
    . "Name: $name\n"
    . "Email: $email\n"
    . 'Company: ' . ($company !== '' ? $company : '-') . "\n"
    . 'Area of interest: ' . ($service !== '' ? $service : '-') . "\n\n"
    . "Message:\n" . ($message !== '' ? $message : '-') . "\n";

$subject = '=?UTF-8?B?' . base64_encode("Website enquiry from $name") . '?=';
$headers = implode("\r\n", [
    'From: IdeasClique Website <' . SENDER . '>',
    "Reply-To: $email",
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: base64',
]);

$sent = mail(RECIPIENT, $subject, chunk_split(base64_encode($body)), $headers, '-f' . SENDER);
respond($sent ? 200 : 502, $sent);
