# Renick.Survey

Just a simple OctoberCMS plugin for doing surveys on your website.
These features are provided by the initial release of
this plugin:

- [x] Create surveys with multiple options
- [x] Choose between single- and multiple-choice
- [x] Show results of a survey
- [x] Optionally get users details, e.g. for a raffle
- [x] Optionally get a notification email after each submit
- [x] Localized in German and English
- [x] Fully customizable via Twig templates
- [x] Fully customizable via CSS
- [x] Simple Spam Protection
- [x] Results page with charts and all answers (print support)
- [x] Excel export (.xlsx) of the results, without extra dependencies

The plugin is built without own CSS, or Javascript dependencies.
The component twig layout is built with Bootstrap 5 classnames.
If you do not have Bootstrap 5 in-place, please just add your own CSS.

## How to use

Just install the plugin and add the component to your page.
Create a survey and add some options.
Then select the survey in the component settings and you are done.

It may is needed to add some own css rules to your theme to make it look nice for your template.
The component is built with Bootstrap 5 classnames.

The notification email is optional, and enables notifications for a survey.

## Technical notice

Also there are environment variables which may are handy:

```bash
# limit the number of submissions per IP address (limit per survey), 0 disables the IP limit
SURVEY_IP_ADDRESS_LIMIT=99

#  enable, disable using a cookie to prevent double answers
SURVEY_COOKIE_LIMIT=false

# disable all email notifications (may temporarily)
SURVEY_DISABLE_EMAIL_NOTIFICATIONS=true

# allow only one submit event per email and survey
SURVEY_UNIQUE_EMAIL=true

# max submit attempts per minute per IP address and survey, 0 disables the throttle
SURVEY_THROTTLE_PER_MINUTE=20
```

The same options can be set in `config/renick/survey/config.php` of your October installation
(`cookie_limit`, `ip_address_limit`, `unique_email`, `disable_email_notifications`, `throttle_per_minute`).
Behind a reverse proxy, make sure proxies are trusted, otherwise all visitors share one IP address.

## Excel export

Open the results page of a survey and use the export button. The file contains the answers per
participant and a summary per option. It is written without extra libraries and needs the PHP `zip` extension;
without it a CSV file (UTF-8, semicolon separated) is delivered instead. Anonymous surveys contain no personal columns.

## Found a bug?

Feel free to open an issue on GitHub, suggest a pull-request or reach out to me
using the [contact](https://www.renick.io) form on my homepage.
