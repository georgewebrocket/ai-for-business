<?php

class conn1
{
    static $connstr = 'mysql:host=localhost;port=3306;dbname=aaw_aipublisher;charset=utf8';
    static $username = 'aaw_aipublisher';
    static $password = '?M3j0F%h0nqjUfzj';
}

class app
{
    static $project_name = "AI-Publisher";
    static $host = "https://aipublisher.aaw.gr/";
    static $slug = "AIPUBLISHER"; // for cookies and session variables

    static $root = "https://aipublisher.aaw.gr/";

    static $app_domain = "aipublisher.aaw.gr";

    static $ADMIN_LANG = 'gr';

    static $SMTP_USERNAME='info@rocketone.site';
    static $SMTP_PASSWORD='MAckyWaLthAnALIG';
    static $SMTP_HOST='smtp.eu.mailgun.org';
    static $SMTP_PORT=587;
    static $SMTP_SECURE='tls';
    static $SMTP_CHARSET='UTF-8';
    static $SMTP_FROM = "info@rocketone.site";
    
}

class openai
{
    static $key = ''; // AI API keys are stored per account in settings.key_code = "ai-api-key".
}
