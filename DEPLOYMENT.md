# Deploying Laravel Notes App on Render.com

This guide will help you deploy the Notes App on Render.com using Docker with a free domain.

## Prerequisites

- A [Render.com](https://render.com) account (free tier available)
- A [GitHub](https://github.com) or [GitLab](https://gitlab.com) repository with your project
- A database service (You can use Render's PostgreSQL or [Railway.app](https://railway.app) for MySQL)
- [Pusher](https://pusher.com) account for real-time features (free tier available)

## Deployment Steps

### 1. Set Up the Database

#### Option A: Using Render's PostgreSQL (Free tier available)

1. Log in to your Render dashboard
2. Create a new PostgreSQL database service
3. Note the connection details (host, database, user, password)
4. You'll need to change the Laravel DB_CONNECTION to 'pgsql' in the next steps

#### Option B: Using Railway MySQL (Free tier available)

1. Sign up at [Railway.app](https://railway.app)
2. Create a new project with a MySQL database
3. Note the connection details

### 2. Create Pusher App (For Real-time Features)

1. Sign up at [Pusher](https://pusher.com)
2. Create a new Channels app
3. Note the app_id, key, secret, and cluster

### 3. Deploy on Render.com

1. Log in to your Render dashboard
2. Click "New" and select "Web Service"
3. Connect your GitHub/GitLab repository
4. Select the repository with your Laravel Notes App
5. Configure the service:
   - **Name**: notes-app (or your preferred name)
   - **Environment**: Docker
   - **Branch**: main (or your default branch)
   - **Root Directory**: (Leave blank if Dockerfile is in root)
   - **Instance Type**: Free (or any paid plan for better performance)

6. Add environment variables:
   ```
   APP_NAME=NotesApp
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://your-app-name.onrender.com
   ASSET_URL=https://your-app-name.onrender.com
   
   DB_CONNECTION=mysql (or pgsql for PostgreSQL)
   DB_HOST=your-db-host
   DB_PORT=3306 (or 5432 for PostgreSQL)
   DB_DATABASE=your-db-name
   DB_USERNAME=your-db-username
   DB_PASSWORD=your-db-password
   
   BROADCAST_DRIVER=pusher
   CACHE_DRIVER=file
   FILESYSTEM_DISK=local
   QUEUE_CONNECTION=sync
   SESSION_DRIVER=file
   SESSION_LIFETIME=120
   
   PUSHER_APP_ID=your-pusher-app-id
   PUSHER_APP_KEY=your-pusher-app-key
   PUSHER_APP_SECRET=your-pusher-app-secret
   PUSHER_APP_CLUSTER=your-pusher-cluster
   ```

7. Click "Create Web Service"

### 4. Get a Free Domain

#### Option A: Use Render's Free Subdomain

Your app will automatically be available at `https://your-app-name.onrender.com`

#### Option B: Use Freenom for a Free Domain

1. Visit [Freenom](https://www.freenom.com)
2. Search for an available domain (.tk, .ml, .ga, .cf, or .gq)
3. Register the free domain
4. In your Render dashboard, go to your web service
5. Navigate to "Settings" → "Custom Domains"
6. Add your Freenom domain
7. Follow the DNS configuration instructions
   - On Freenom, add a CNAME record pointing to your Render URL

### 5. Verify Deployment

1. Wait for the build and deployment to complete (10-15 minutes)
2. Visit your domain to confirm the app is running
3. Check that all features work correctly

## Troubleshooting

- **If the app doesn't load**: Check the logs in your Render dashboard
- **Database connection issues**: Verify your environment variables
- **Real-time features not working**: Confirm Pusher credentials are correct

## Maintenance and Updates

To update your deployed app:
1. Push changes to your GitHub/GitLab repository
2. Render will automatically rebuild and deploy the updated version

## Additional Information

- Free tier on Render.com has limitations (like spinning down after inactivity)
- For production use, consider upgrading to a paid plan
- Regular database backups are recommended 