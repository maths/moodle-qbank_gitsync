
This plugin requires the [import as new version](https://github.com/maths/moodle-qbank_importasversion) plugin as well.

# Setting up the Webservice

- Add the gitsync plugin to Moodle.  E.g. from the moodle directory clone the code as follows.

    git clone https://github.com/maths/moodle-qbank_gitsync.git question/bank/gitsync

- Run the setup script in the cli folder on the Moodle server:
  - `php webservicesetup.php`
  - This creates a user `ws-gitsync-user` and role `ws-gitsync-role`.
  - The role has capabilities `webservice/rest:use`, `qbank/gitsync:importquestions`, `qbank/gitsync:exportquestions`, `qbank/gitsync:deletequestions` and `qbank/gitsync:listquestions`.
  - The user is assigned the role.
  - The webservice is enabled and the user is authorised to use the service. (Site administration/Server/Web services/External services/your_service_name/Edit)
  - File upload is enabled within the webservice. (Site administration/Server/Web services/External services/your_service_name/Edit/Show more/Can upload files)
- Go to Site administration/Server/Web services/Manage tokens in Moodle and create a token for the user `ws-gitsync-user`.
![Screenshot of token creation.](../images/Add_token.png)
- Add roles for the user to give them access to the required courses and questions.  If you would like them to have site-wide access, go to Site Administration/Users/Permissions/Assign system roles and give `ws-gitsync-user` Manager role for the webservice to have access to all questions on the site.  If you only want them to have access to particular courses, then make `ws-gitsync-user` Manager on courses individually.
- You can test using [Postman](https://www.postman.com/downloads/) if you know a question id and its course name:
  - URL: Your_Moodle_root_address/webservice/rest/server.php
  - Params:
    - wstoken: _Your token created above_
    - wsfunction: qbank_gitsync_export_question
    - moodlewsrestformat: json
    - questionid: _Your question id_
    - contextlevel: 10
    - coursename: _Your course name_
    - modulename:
    - coursecategory:

![Screenshot of Postman.](../images/Postman.png)
