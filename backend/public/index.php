<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

// Create App
$app = AppFactory::create();

// Add body parsing middleware
$app->addBodyParsingMiddleware();

// Add error middleware
$app->addErrorMiddleware(true, true, true);

// CORS middleware
$app->add(function (Request $request, $handler): Response {
    $response = $handler->handle($request);
    return $response
        ->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

// Handle preflight OPTIONS requests
$app->options('/{routes:.+}', function (Request $request, Response $response): Response {
    return $response;
});

// Database configuration - SQLite
$dbPath = __DIR__ . '/../data/calendar.db';

// Initialize database connection
function getDatabase(string $dbPath): PDO
{
    $dir = dirname($dbPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Create events table if it doesn't exist
    $pdo->exec('
        CREATE TABLE IF NOT EXISTS events (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            date TEXT NOT NULL,
            time TEXT,
            description TEXT,
            instructor TEXT,
            created_at TEXT DEFAULT CURRENT_TIMESTAMP,
            updated_at TEXT
        )
    ');
    
    return $pdo;
}

// Get database connection
$db = getDatabase($dbPath);

// Get all events
$app->get('/api/events', function (Request $request, Response $response) use ($db): Response {
    $stmt = $db->query('SELECT * FROM events ORDER BY date, time');
    $events = $stmt->fetchAll();
    $response->getBody()->write(json_encode($events));
    return $response->withHeader('Content-Type', 'application/json');
});

// Get events for a specific date
$app->get('/api/events/{date}', function (Request $request, Response $response, array $args) use ($db): Response {
    $date = $args['date'];
    $stmt = $db->prepare('SELECT * FROM events WHERE date = :date ORDER BY time');
    $stmt->execute([':date' => $date]);
    $events = $stmt->fetchAll();
    $response->getBody()->write(json_encode($events));
    return $response->withHeader('Content-Type', 'application/json');
});

// Create a new event
$app->post('/api/events', function (Request $request, Response $response) use ($db): Response {
    $data = $request->getParsedBody();
    
    // Validate required fields
    if (empty($data['title']) || empty($data['date'])) {
        $response->getBody()->write(json_encode(['error' => 'Title and date are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    
    $stmt = $db->prepare('
        INSERT INTO events (title, date, time, description, instructor, created_at)
        VALUES (:title, :date, :time, :description, :instructor, :created_at)
    ');
    
    $stmt->execute([
        ':title' => htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8'),
        ':date' => $data['date'],
        ':time' => $data['time'] ?? '',
        ':description' => htmlspecialchars($data['description'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':instructor' => htmlspecialchars($data['instructor'] ?? '', ENT_QUOTES, 'UTF-8'),
        ':created_at' => date('Y-m-d H:i:s')
    ]);
    
    $newId = (int) $db->lastInsertId();
    
    // Fetch the created event
    $stmt = $db->prepare('SELECT * FROM events WHERE id = :id');
    $stmt->execute([':id' => $newId]);
    $newEvent = $stmt->fetch();
    
    $response->getBody()->write(json_encode($newEvent));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
});

// Update an event
$app->put('/api/events/{id}', function (Request $request, Response $response, array $args) use ($db): Response {
    $id = (int) $args['id'];
    $data = $request->getParsedBody();
    
    // Check if event exists
    $stmt = $db->prepare('SELECT * FROM events WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existingEvent = $stmt->fetch();
    
    if (!$existingEvent) {
        $response->getBody()->write(json_encode(['error' => 'Event not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    
    $stmt = $db->prepare('
        UPDATE events 
        SET title = :title, date = :date, time = :time, 
            description = :description, instructor = :instructor, updated_at = :updated_at
        WHERE id = :id
    ');
    
    $stmt->execute([
        ':id' => $id,
        ':title' => htmlspecialchars($data['title'] ?? $existingEvent['title'], ENT_QUOTES, 'UTF-8'),
        ':date' => $data['date'] ?? $existingEvent['date'],
        ':time' => $data['time'] ?? $existingEvent['time'],
        ':description' => htmlspecialchars($data['description'] ?? $existingEvent['description'], ENT_QUOTES, 'UTF-8'),
        ':instructor' => htmlspecialchars($data['instructor'] ?? $existingEvent['instructor'], ENT_QUOTES, 'UTF-8'),
        ':updated_at' => date('Y-m-d H:i:s')
    ]);
    
    // Fetch the updated event
    $stmt = $db->prepare('SELECT * FROM events WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $updatedEvent = $stmt->fetch();
    
    $response->getBody()->write(json_encode($updatedEvent));
    return $response->withHeader('Content-Type', 'application/json');
});

// Delete an event
$app->delete('/api/events/{id}', function (Request $request, Response $response, array $args) use ($db): Response {
    $id = (int) $args['id'];
    
    // Check if event exists
    $stmt = $db->prepare('SELECT * FROM events WHERE id = :id');
    $stmt->execute([':id' => $id]);
    $existingEvent = $stmt->fetch();
    
    if (!$existingEvent) {
        $response->getBody()->write(json_encode(['error' => 'Event not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    
    $stmt = $db->prepare('DELETE FROM events WHERE id = :id');
    $stmt->execute([':id' => $id]);
    
    $response->getBody()->write(json_encode(['message' => 'Event deleted successfully']));
    return $response->withHeader('Content-Type', 'application/json');
});

// Health check endpoint
$app->get('/api/health', function (Request $request, Response $response) use ($db): Response {
    try {
        $db->query('SELECT 1');
        $response->getBody()->write(json_encode([
            'status' => 'ok',
            'database' => 'connected',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    } catch (Exception $e) {
        $response->getBody()->write(json_encode([
            'status' => 'error',
            'database' => 'disconnected',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        return $response->withStatus(500)->withHeader('Content-Type', 'application/json');
    }
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
