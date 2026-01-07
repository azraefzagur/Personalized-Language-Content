CREATE DATABASE IF NOT EXISTS english_platform
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE english_platform;

-- USERS
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('student','admin') NOT NULL DEFAULT 'student',
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(120) NOT NULL,
  avatar_url VARCHAR(255) NULL,
  level VARCHAR(10) NULL,                -- A1/A2/B1/B2/C1
  placement_completed TINYINT(1) NOT NULL DEFAULT 0,
  theme ENUM('light','dark') NOT NULL DEFAULT 'light',
  points INT NOT NULL DEFAULT 0,
  streak INT NOT NULL DEFAULT 0,
  last_active_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- QUOTE OF THE DAY
CREATE TABLE quotes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  quote_text VARCHAR(255) NOT NULL,
  author VARCHAR(120) NULL
);

-- QUESTIONS
CREATE TABLE questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  skill ENUM('vocab','grammar','reading','listening','writing') NOT NULL,
  difficulty TINYINT NOT NULL DEFAULT 1,   -- 1..5
  is_placement TINYINT(1) NOT NULL DEFAULT 0,
  prompt TEXT NOT NULL,
  choices_json JSON NULL,                  -- MCQ for vocab/grammar/reading/listening
  correct_answer TEXT NULL,                -- for writing or MCQ key
  media_url VARCHAR(255) NULL,             -- audio/image
  hint TEXT NULL,
  explanation TEXT NULL,
  example_sentence TEXT NULL,
  tags VARCHAR(255) NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- LESSONS (content library)
CREATE TABLE lessons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(180) NOT NULL,
  level VARCHAR(10) NULL,
  skill ENUM('vocab','grammar','reading','listening','writing') NOT NULL,
  material_type ENUM('reading','visual') NOT NULL,
  body_html MEDIUMTEXT NOT NULL,
  difficulty TINYINT NOT NULL DEFAULT 1,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ATTEMPTS (history + analytics)
CREATE TABLE question_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  question_id INT NOT NULL,
  is_correct TINYINT(1) NOT NULL,
  user_answer TEXT NULL,
  attempt_type ENUM('placement','practice') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(question_id) REFERENCES questions(id) ON DELETE CASCADE,
  INDEX(user_id), INDEX(question_id)
);

-- FAVORITES (lessons or questions)
CREATE TABLE favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  fav_type ENUM('lesson','question') NOT NULL,
  ref_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_fav(user_id, fav_type, ref_id),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(user_id)
);

-- NOTEBOOK
CREATE TABLE notebook_entries (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  term VARCHAR(120) NOT NULL,
  meaning VARCHAR(255) NULL,
  note TEXT NULL,
  example TEXT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(user_id)
);

-- REPORTS / ERROR FEEDBACK
CREATE TABLE reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reporter_id INT NOT NULL,
  role ENUM('student','admin') NOT NULL,
  category VARCHAR(60) NOT NULL,
  page VARCHAR(120) NULL,
  message TEXT NOT NULL,
  status ENUM('new','reviewing','resolved') NOT NULL DEFAULT 'new',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(reporter_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX(reporter_id), INDEX(status)
);

-- BADGES
CREATE TABLE badges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(60) NOT NULL UNIQUE,
  title VARCHAR(120) NOT NULL,
  description VARCHAR(255) NOT NULL,
  points_required INT NOT NULL DEFAULT 0
);

CREATE TABLE user_badges (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  badge_id INT NOT NULL,
  earned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_badge(user_id, badge_id),
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY(badge_id) REFERENCES badges(id) ON DELETE CASCADE
);

-- REMINDER LOG
CREATE TABLE reminder_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  reason VARCHAR(120) NOT NULL,
  FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Seed
INSERT INTO quotes(quote_text, author) VALUES
('Small steps every day add up to big results.', 'Unknown'),
('Consistency beats intensity.', 'Unknown'),
('Progress, not perfection.', 'Unknown');

INSERT INTO badges(code,title,description,points_required) VALUES
('starter','Starter','Earn your first 50 points.',50),
('steady','Steady','Reach 200 points.',200),
('climber','Climber','Reach 500 points.',500);

-- Example placement questions
INSERT INTO questions(skill,difficulty,is_placement,prompt,choices_json,correct_answer,hint,explanation,example_sentence,tags)
VALUES
('grammar',1,1,'Choose the correct sentence.',
 JSON_ARRAY('She go to school every day.','She goes to school every day.','She going to school every day.','She gone to school every day.'),
 '1','Look at subject-verb agreement.','Third person singular takes -s in present simple.','She goes to school every day.','present simple'),
('vocab',1,1,'What is the closest meaning of "tiny"?',
 JSON_ARRAY('Huge','Small','Angry','Fast'),
 '1','Think about size.','"Tiny" means very small.','A tiny bird sat on the window.','adjectives'),
('reading',1,1,'Read: "Tom is late because of traffic." Why is Tom late?',
 JSON_ARRAY('Because he slept.','Because of traffic.','Because he is sick.','Because he forgot.'),
 '1',NULL,'The sentence says "because of traffic".',NULL,'reading');
