# Running the scripts locally

## Prerequisites (TODO - expand!)
- Install Git
- Install PHP

## Setup
- Open a terminal and clone this repository `git clone https://github.com/maths/moodle-qbank_gitsync.git gitsync`
- In the `cli` folder, update `$moodleinstances` in `importrepo.php` with the URL and name of your Moodle instance.
- In the same file add any defaults for command line options such as `token`.
- `php importrepo.php -t 4ec7cd3f62e08f595df5e9c90ea7cfcd -i edmundlocal -d "C:\question_repos\first\questions" -l system`

