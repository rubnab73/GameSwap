# 🎮 GameSwap - Game Lending Platform

GameSwap is a web-based platform that enables gamers to lend and borrow video games within their local community. Built with PHP and MySQL, it provides a secure and user-friendly environment for game sharing.

## ✨ Features

### For Gamers
- 🎯 Share your games with the community
- 🔄 Borrow games from other users
- ⭐ Rate games and lenders
- 📊 Track your lending/borrowing history
- 🏆 Earn points through platform participation

### Core Functionality
- **User Management**
  - Secure authentication system
  - Profile management
  - Points-based reputation system

- **Game Management**
  - Add/Edit/Delete games
  - Upload game images
  - Track game availability status
  - Categorize by console and genre

- **Borrowing System**
  - Request to borrow games
  - Approve/reject borrow requests
  - Automatic due date tracking
  - Return game functionality
  - Late return notifications

- **Rating System**
  - Rate games after playing
  - Rate lenders after returning
  - Automatic 5-star rating for on-time returns
  - View average ratings

- **Points System**
  - +10 points for sharing a game
  - +20 points when someone returns your game
  - +10 points for returning games on time

## 🛠️ Technology Stack

- **Backend:** PHP 7.4+
- **Database:** MySQL 5.7+
- **Frontend:**
  - HTML5
  - CSS3
  - Bootstrap 5
  - JavaScript
  - Font Awesome Icons
- **Server:** XAMPP/Apache

## 📋 Prerequisites

- XAMPP (or similar AMP stack)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web browser with JavaScript enabled

## 🚀 Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/GameSwap.git
   ```

2. Move the project to your web server directory:
   ```bash
   mv GameSwap /path/to/xampp/htdocs/
   ```

3. Create a new MySQL database:
   ```sql
   CREATE DATABASE gameswap;
   ```

4. Import the database schema:
   ```bash
   mysql -u root gameswap < sql/schema.sql
   ```

5. Configure the database connection:
   - Edit `config/database.php` with your database credentials

6. Set up the uploads directory:
   ```bash
   mkdir -p uploads/games
   chmod 777 uploads/games
   ```

7. Access the application:
   ```
   http://localhost/GameSwap
   ```

## 🔒 Security Features

- Password hashing using modern algorithms
- Input sanitization
- Prepared SQL statements
- Session-based authentication
- CSRF protection
- XSS prevention

## 📱 Responsive Design

The platform is fully responsive and works seamlessly on:
- 💻 Desktop computers
- 📱 Mobile phones
- 📟 Tablets

## 🎯 Point System

Users can earn points through various activities:
- Sharing a game: +10 points
- When someone returns your game: +20 points
- Returning a game on time: +10 points

## 👥 Community Features

- Leaderboard system
- User profiles
- Game reviews and ratings
- Real-time notifications
- Community statistics

## 📚 Database Structure

The system uses 5 main tables:
- users
- games
- borrow_requests
- ratings
- notifications

## 🤝 Contributing

Feel free to fork the repository and submit pull requests. For major changes, please open an issue first to discuss the proposed change.

## 📝 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🙏 Acknowledgments

- Bootstrap team for the amazing UI framework
- Font Awesome for the beautiful icons
- XAMPP team for the development environment
- All contributors and testers