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

The plugin is built without own CSS, or Javascript dependencies.
The component twig layout is built with Bootstrap 5 classnames.
If you do not have Bootstrap 5 in-place, please just add your own CSS.

Also there are environment variables which may are handy:

```bash
# limit the number of surveys per IP address (limit per survey)
SURVEY_IP_ADDRESS_LIMIT=99

#  enable, disable using a cookie to prevent double answers
SURVEY_COOKIE_LIMIT=false
```

## Found a bug?

Feel free to open an issue on GitHub, suggest a pull-request or reach out to me
using the [contact](https://www.renick.io) form on my homepage.
