#!/bin/bash

# Make the script executable
chmod +x start-collaboration-server.sh

# Check if Redis is installed
if ! command -v redis-server &> /dev/null; then
    echo "Redis is not installed. Installing Redis..."
    brew install redis
fi

# Start Redis in the background if not already running
if ! pgrep -x "redis-server" > /dev/null; then
    echo "Starting Redis server..."
    brew services start redis || redis-server &
    sleep 2
fi

# Make sure Composer dependencies are installed
if [ ! -d "vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install
fi

# Make sure NPM dependencies are installed
if [ ! -d "node_modules" ]; then
    echo "Installing NPM dependencies..."
    npm install
fi

# Start Laravel Echo Server
echo "Starting Laravel Echo Server..."
npx laravel-echo-server start &
echo "Laravel Echo Server started."

# Start Laravel server if not already running
echo "Starting Laravel development server..."
php artisan serve &
echo "Laravel server started."

echo "All services started! Real-time collaboration should now work."
echo "Open your application in two different browsers or incognito windows to test."
echo "Press Ctrl+C to stop all services."

# Keep script running and catch Ctrl+C to clean up
trap "echo 'Stopping all services...'; pkill -f 'laravel-echo-server'; pkill -f 'php artisan serve'; brew services stop redis; echo 'All services stopped.'" SIGINT

# Wait indefinitely
while true; do
    sleep 1
done 