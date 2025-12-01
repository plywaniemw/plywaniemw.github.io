# Swimming Calendar Application / Aplikacja Kalendarza Pływania

A web application for managing swimming classes with a calendar interface.
Aplikacja webowa do zarządzania zajęciami pływackimi z interfejsem kalendarza.

## Features / Funkcje

- 📅 Calendar view for displaying swimming classes
- ➕ Add new classes with date, time, instructor, and description
- ✏️ Edit existing classes
- 🗑️ Delete classes
- 📱 Responsive design for mobile and desktop
- 💾 SQLite database storage for persistent data

## Technology Stack / Stos Technologiczny

### Backend
- **PHP 8.x** with **Slim Framework 4** - lightweight RESTful API
- **SQLite** database for data storage

### Frontend
- **Vue.js 3** - reactive JavaScript framework
- **Vite** - fast build tool and dev server
- Pure CSS with modern styling

## Project Structure / Struktura Projektu

```
├── backend/
│   ├── public/
│   │   └── index.php          # API entry point
│   ├── data/
│   │   └── calendar.db        # SQLite database (auto-created)
│   ├── composer.json
│   └── composer.lock
├── frontend/
│   ├── src/
│   │   ├── App.vue            # Main Vue component
│   │   ├── main.js            # Vue app initialization
│   │   └── style.css          # Global styles
│   ├── index.html
│   ├── vite.config.js
│   └── package.json
└── README.md
```

## Installation / Instalacja

### Prerequisites / Wymagania

- PHP 8.0 or higher
- Composer
- Node.js 18+ and npm

### Backend Setup

```bash
cd backend
composer install
```

### Frontend Setup

```bash
cd frontend
npm install
```

## Running the Application / Uruchamianie Aplikacji

### Development Mode

1. Start the PHP backend server:
```bash
cd backend
php -S localhost:8000 -t public
```

2. In another terminal, start the frontend dev server:
```bash
cd frontend
npm run dev
```

3. Open http://localhost:3000 in your browser

### Production Build

1. Build the frontend:
```bash
cd frontend
npm run build
```

2. Configure your web server to serve the backend API and frontend static files.

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/events` | Get all events |
| GET | `/api/events/{date}` | Get events for a specific date |
| POST | `/api/events` | Create a new event |
| PUT | `/api/events/{id}` | Update an event |
| DELETE | `/api/events/{id}` | Delete an event |
| GET | `/api/health` | Health check |

### Event Object Structure

```json
{
  "id": 1,
  "title": "Swimming Lesson / Lekcja Pływania",
  "date": "2024-01-15",
  "time": "10:00",
  "instructor": "Jan Kowalski",
  "description": "Beginner level swimming class",
  "created_at": "2024-01-01 10:00:00"
}
```

## Environment Variables / Zmienne Środowiskowe

### Frontend
- `VITE_API_URL` - Backend API URL (default: `/api`)

## License / Licencja

ISC