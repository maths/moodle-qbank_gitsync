# Running the scripts locally on an external file system

Gitsync is run from and stores question files on your local computer not on the Moodle server. First you need to install it and [set it up as a plugin within Moodle](webservicesetup.md) but then you also need to download and set it up locally as described below.

## Prerequisites
- Install Git

  Go to `https://git-scm.com/book/en/v2/Getting-Started-Installing-Git` and follow the instructions for your operating system.

  For Windows:
  - Go to `https://git-scm.com/download/win` and download the standalone installer (probably '64-bit Git for Windows Setup').
  - Run the installer and fight through the setup questions. The defaults are fine for most of them but if you've never heard of Vim
  don't choose it as your default editor.
  - Set your identity:
    - `git config --global user.name "John Doe"`
    - `git config --global user.email johndoe@example.com`

- Install PHP
  - Go to `https://www.php.net/manual/en/install.php` and follow the instructions for your operating system.

  For Windows:
  - Go to `https://windows.php.net/download/` and download the non-thread safe Zip file of PHP 8.1(?).
  - Unzip the folder into a folder of your choice.
  - Add the folder to the `Path` variable of your system via 'Edit System Environment Variables'.

  You will need to enable a PHP extension. On the command line, run `php -i`. This will display pages of information but near the top there should be a line similar to `Loaded Configuration File => C:\Program Files\php-x64\php.ini`. Open the shown file as an administrator and search for `;extension=curl`. Remove the semi-colon from this line and save.

## Setup
- Open a terminal and clone this repository `git clone https://github.com/maths/moodle-qbank_gitsync.git gitsync`. The repository will be downloaded in a folder `gitsync` inside your current folder.
- The repository will default to the `main` branch but you may need to switch to another branch if you're testing new features (e.g. `dev` for initial beta testing). In the gitsync directory e.g. `git checkout dev`.
- In the `cli` folder within `gitsync`, make a copy of `config_sample.txt` and name it `config.php` within the `cli` folder.
- Update `config.php` with the URLs and names of your Moodle instances.
- In the same file, add tokens for each of your Moodle instances and set a default instance. (See [Setting up the Webservice](webservicesetup.md) for token creation.)
- In the same file, update rootdirectory to be the directory where you plan to keep your question repositories.

