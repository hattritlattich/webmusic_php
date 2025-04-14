<?php
require_once __DIR__ . '/../../app/models/SongModel.php';

header('Content-Type: application/json');

$artistId = $_GET['artist_id'] ?? 0;

if (!$artistId) {
    http_response_code(400);
    echo json_encode(['error' => 'Artist ID is required']);
    exit;
}

try {
    $songModel = new SongModel();
    $songs = $songModel->getSongsByArtist($artistId);
    echo json_encode($songs);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
} 