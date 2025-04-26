# Notes App - Modern Note Management Application

A full-featured web application for creating, managing, and sharing notes with real-time collaboration and offline capabilities.

![Notes App Screenshot](screenshots/notes-app.png)

## ✨ Features

### Core Functionality
- Create, read, update, and delete notes with rich text content
- Pin important notes to the top of your list
- Organize notes with custom labels
- Attach images and files to notes
- Password-protect sensitive notes
- Search and filter through your notes collection

### Collaboration & Sharing
- Share notes with other users with customizable permissions (read-only/edit)
- Real-time collaborative editing with presence indicators
- Notification system for shared notes

### User Experience
- Responsive design that works on desktop, tablet, and mobile devices
- Dark/light theme options and customizable font sizes
- User profile and avatar management
- Email notifications for important actions

### Advanced Features
- **Progressive Web App (PWA)** with offline capabilities
- Continue creating and editing notes even without internet connection
- Automatic synchronization when connection is restored
- Install as a standalone application on supported devices

## 🚀 Technologies Used

### Backend
- **PHP 8.2** with Laravel 12.x framework
- **MySQL/SQLite** database
- **Laravel Echo Server** for WebSocket support
- **Pusher** for real-time event broadcasting

### Frontend
- **Bootstrap 5** for responsive UI components
- **SASS** for custom styling
- **JavaScript** with modern ES6+ features
- **Laravel Echo** and **Pusher.js** for real-time client updates
- **Service Workers** for offline functionality
- **IndexedDB** for client-side storage

### Development & Deployment
- **Vite** for modern frontend asset bundling
- **Composer** for PHP dependency management
- **NPM** for JavaScript dependency management

## 📋 Requirements

- PHP 8.2 or higher
- Composer 2.x
- Node.js 18.x or higher with NPM
- MySQL 8.x, MariaDB, or SQLite
- Web server (Apache/Nginx) or PHP's built-in server for development

## 🔧 Installation

1. **Clone the repository**:
   ```bash
   git clone https://github.com/iannwendy/NOTEAPP.git
   cd notes-app
   ```

2. **Install PHP dependencies**:
   ```bash
   composer install
   ```

3. **Install JavaScript dependencies**:
   ```bash
   npm install
   ```

4. **Environment Setup**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

5. **Configure your database in the `.env` file**:
   ```
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=notes_app
   DB_USERNAME=root
   DB_PASSWORD=
   ```
   
   For SQLite (simpler setup for development):
   ```
   DB_CONNECTION=sqlite
   # Comment out or remove the other DB_* variables
   ```
   Then create the SQLite database file:
   ```bash
   touch database/database.sqlite
   ```

6. **Configure Pusher for real-time features in `.env`**:
   ```
   PUSHER_APP_ID=your_app_id
   PUSHER_APP_KEY=your_app_key
   PUSHER_APP_SECRET=your_app_secret
   PUSHER_APP_CLUSTER=ap1
   ```

7. **Configure mail settings in `.env` for notifications**:
   ```
   MAIL_MAILER=smtp
   MAIL_HOST=your_smtp_host
   MAIL_PORT=your_smtp_port
   MAIL_USERNAME=your_username
   MAIL_PASSWORD=your_password
   MAIL_ENCRYPTION=tls
   MAIL_FROM_ADDRESS=your_email@example.com
   MAIL_FROM_NAME="${APP_NAME}"
   ```

8. **Run database migrations**:
   ```bash
   php artisan migrate
   ```

9. **Create storage symbolic link for attachments**:
   ```bash
   php artisan storage:link
   ```

## 🚦 Running the Application

### Development Mode

1. **Compile assets and start the Vite dev server**:
   ```bash
   npm run dev
   ```

2. **Start the Laravel development server**:
   ```bash
   php artisan serve
   ```

3. **Start the Echo server for real-time features** (in a new terminal):
   ```bash
   ./start-collaboration-server.sh
   ```

4. Access the application at `http://localhost:8000`

### Production Deployment

1. **Compile and minify assets for production**:
   ```bash
   npm run build
   ```

2. Configure your web server (Nginx/Apache) to serve the application from the `public` directory.

3. Set up a process manager like Supervisor to keep the Echo server running.

## 📱 Progressive Web App (PWA) Features

The application supports PWA installation and offline functionality:

- **Installable**: Add to home screen on mobile devices or install as a desktop app
- **Offline Support**: Continue creating and editing notes without internet connection
- **Background Sync**: Automatically synchronize changes when connection is restored
- **Cached Resources**: Fast loading with cached assets and previously viewed notes

To take full advantage of the offline capabilities:
1. Visit the application in a supported browser (Chrome, Edge, etc.)
2. Install it when prompted or use the browser's "Install" option
3. The app will continue to work even when offline

## 📖 Usage Guide

### Managing Notes
- Create new notes from the home page
- Edit notes by clicking on them and selecting "Edit"
- Pin important notes to keep them at the top of your list
- Add labels to organize and filter your notes
- Attach files by clicking the "Attach" button when editing a note
- Password-protect sensitive notes from the note options menu

### Sharing & Collaboration
- Share notes with other users by clicking "Sharing" on a note
- Set permissions to read-only or edit for each collaborator
- See who's editing a note in real-time with user indicators
- Receive notifications when notes are shared with you

### Offline Usage
- Notes you've viewed will be available offline
- Create and edit notes while offline
- Changes will sync automatically when you reconnect
- A sync indicator shows pending changes waiting to be synchronized

## 👥 Contributors

This project was created by Your Team Name. Contributions are welcome! Please feel free to submit a Pull Request.

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
