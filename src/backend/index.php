<?php
header('Content-Type: application/json');

$WEBHOOK_URL = 'https://discord.com/api/webhooks/1249680253094334484/qpw0h0LpKJugKgzZRbMeTF0j2i3LbXeie9hg1xPgB5DEk9YYFYmyij2z2NgR80y5aNtD';
$COOLDOWN_PERIOD = 2 * 60 * 60; // 2 jam dalam detik
$last_sent_file = 'last_sent_times.json';

function get_client_ip() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'];
    }
}

function load_last_sent_times() {
    global $last_sent_file;
    if (file_exists($last_sent_file)) {
        $data = file_get_contents($last_sent_file);
        return json_decode($data, true);
    }
    return [];
}

function save_last_sent_times($last_sent_times) {
    global $last_sent_file;
    $data = json_encode($last_sent_times);
    file_put_contents($last_sent_file, $data);
}

function send_message($ip_address, $username, $message) {
    global $WEBHOOK_URL, $COOLDOWN_PERIOD;

    $last_sent_times = load_last_sent_times();
    $current_time = time();
    if (isset($last_sent_times[$ip_address]) && ($current_time - $last_sent_times[$ip_address] < $COOLDOWN_PERIOD)) {
        return ['status' => 'error', 'message' => 'Tunggu 2 jam sebelum mengirim pesan lagi.'];
    }

    $embed = [
        'title' => 'Pesan Baru dari ' . $username,
        'description' => $message,
        'color' => hexdec('123456'),
        'footer' => [
            'text' => 'Dikirim pada ' . date('Y-m-d H:i:s')
        ]
    ];

    $payload = json_encode([
        'embeds' => [$embed]
    ]);

    $ch = curl_init($WEBHOOK_URL);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code == 204) {
        $last_sent_times[$ip_address] = $current_time;
        save_last_sent_times($last_sent_times);
        return ['status' => 'success', 'message' => 'Pesan berhasil dikirim.'];
    } else {
        return ['status' => 'error', 'message' => 'Gagal mengirim pesan.'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $username = $data['username'];
    $message = $data['message'];
    $ip_address = get_client_ip();
    $response = send_message($ip_address, $username, $message);
    echo json_encode($response);
}
?>