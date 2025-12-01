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

// Data file path for simple JSON storage
$dataFile = __DIR__ . '/../data/events.json';

// Helper function to read events
function getEvents(string $dataFile): array
{
    if (!file_exists($dataFile)) {
        return [];
    }
    $content = file_get_contents($dataFile);
    $data = json_decode($content, true);
    return is_array($data) ? $data : [];
}

// Helper function to save events
function saveEvents(string $dataFile, array $events): void
{
    $dir = dirname($dataFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($dataFile, json_encode($events, JSON_PRETTY_PRINT));
}

// Get all events
$app->get('/api/events', function (Request $request, Response $response) use ($dataFile): Response {
    $events = getEvents($dataFile);
    $response->getBody()->write(json_encode($events));
    return $response->withHeader('Content-Type', 'application/json');
});

// Get events for a specific date
$app->get('/api/events/{date}', function (Request $request, Response $response, array $args) use ($dataFile): Response {
    $date = $args['date'];
    $events = getEvents($dataFile);
    $filteredEvents = array_filter($events, fn($event) => $event['date'] === $date);
    $response->getBody()->write(json_encode(array_values($filteredEvents)));
    return $response->withHeader('Content-Type', 'application/json');
});

// Create a new event
$app->post('/api/events', function (Request $request, Response $response) use ($dataFile): Response {
    $data = $request->getParsedBody();
    
    // Validate required fields
    if (empty($data['title']) || empty($data['date'])) {
        $response->getBody()->write(json_encode(['error' => 'Title and date are required']));
        return $response->withStatus(400)->withHeader('Content-Type', 'application/json');
    }
    
    $events = getEvents($dataFile);
    
    // Generate unique ID
    $newId = empty($events) ? 1 : max(array_column($events, 'id')) + 1;
    
    $newEvent = [
        'id' => $newId,
        'title' => htmlspecialchars($data['title'], ENT_QUOTES, 'UTF-8'),
        'date' => $data['date'],
        'time' => $data['time'] ?? '',
        'description' => htmlspecialchars($data['description'] ?? '', ENT_QUOTES, 'UTF-8'),
        'instructor' => htmlspecialchars($data['instructor'] ?? '', ENT_QUOTES, 'UTF-8'),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $events[] = $newEvent;
    saveEvents($dataFile, $events);
    
    $response->getBody()->write(json_encode($newEvent));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
});

// Update an event
$app->put('/api/events/{id}', function (Request $request, Response $response, array $args) use ($dataFile): Response {
    $id = (int) $args['id'];
    $data = $request->getParsedBody();
    
    $events = getEvents($dataFile);
    $index = array_search($id, array_column($events, 'id'));
    
    if ($index === false) {
        $response->getBody()->write(json_encode(['error' => 'Event not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    
    $events[$index] = array_merge($events[$index], [
        'title' => htmlspecialchars($data['title'] ?? $events[$index]['title'], ENT_QUOTES, 'UTF-8'),
        'date' => $data['date'] ?? $events[$index]['date'],
        'time' => $data['time'] ?? $events[$index]['time'],
        'description' => htmlspecialchars($data['description'] ?? $events[$index]['description'], ENT_QUOTES, 'UTF-8'),
        'instructor' => htmlspecialchars($data['instructor'] ?? $events[$index]['instructor'], ENT_QUOTES, 'UTF-8'),
        'updated_at' => date('Y-m-d H:i:s')
    ]);
    
    saveEvents($dataFile, $events);
    
    $response->getBody()->write(json_encode($events[$index]));
    return $response->withHeader('Content-Type', 'application/json');
});

// Delete an event
$app->delete('/api/events/{id}', function (Request $request, Response $response, array $args) use ($dataFile): Response {
    $id = (int) $args['id'];
    
    $events = getEvents($dataFile);
    $index = array_search($id, array_column($events, 'id'));
    
    if ($index === false) {
        $response->getBody()->write(json_encode(['error' => 'Event not found']));
        return $response->withStatus(404)->withHeader('Content-Type', 'application/json');
    }
    
    array_splice($events, $index, 1);
    saveEvents($dataFile, $events);
    
    $response->getBody()->write(json_encode(['message' => 'Event deleted successfully']));
    return $response->withHeader('Content-Type', 'application/json');
});

// Health check endpoint
$app->get('/api/health', function (Request $request, Response $response): Response {
    $response->getBody()->write(json_encode(['status' => 'ok', 'timestamp' => date('Y-m-d H:i:s')]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->run();
