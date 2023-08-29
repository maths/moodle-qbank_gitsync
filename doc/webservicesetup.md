
# Setting up the Webservice

- Add the gitsync plugin to Moodle.
- Run the setup script in the cli folder on the Moodle server:
  - `php webservicesetup.php`
  - This creates a user `ws-gitsync-user` and role `ws-gitsync-role`.
  - The role has capabilities `webservice/rest:use`, `qbank/gitsync:importquestions` and `qbank/gitsync:exportquestions`.
  - The user is assigned the role.
  - The webservice is enabled and the user is authorised to use the service.
  - File upload is enabled.
- Go to Site administration/Server/Web services/Manage tokens in Moodle and create a token for the user.
![Screenshot of token creation.](../images/Add_token.png)
- Add roles for the user to give them access to the required courses and questions. Go to Site Administration/Users/Assign system roles and give them Manager role for the webservice to have access to all questions.
- You can test using [Postman](https://www.postman.com/downloads/) if you know a question id and its course name:
  - URL: Your_Moodle_root_address/webservice/rest/server.php
  - Params:
    - wstoken: _Your token created above_
    - wsfunction: qbank_gitsync_export_question
    - moodlewsrestformat: json
    - questionid: _Your question id_
    - contextlevel: 10
    - coursename: _Your course name_
    - modulename: null
    - coursecategory: null
![Screenshot of Postman.](../images/Postman.png)
