# webtrees module news menu

[![License: GPL v3](https://img.shields.io/badge/License-GPL%20v3-blue.svg)](http://www.gnu.org/licenses/gpl-3.0)
![webtrees major version](https://img.shields.io/badge/webtrees-v2.2.x-green)
![Latest Release](https://img.shields.io/badge/release-v0.3-blue)

This [webtrees](https://www.webtrees.net/) custom module add an extra item to the main menu as a link to a webtrees news. 

## Contents
This Readme contains the following main sections

* [Warning](#warning)
* [Description](#description)
* [Features](#features)
* [Screenshots](#screenshots)
* [Requirements](#requirements)
* [Installation](#installation)
* [Upgrade](#upgrade)
* [Support](#support)
* [License](#license)

<a name="warning"></a>
## Warning

This module makes changes to the Webtrees database, the data entered through the module is not exported to GEDCOM files. Before installing the module on your main site, try to test it.

<a name="description"></a>
## Description

This custom module for webtrees introduces a news management system. It extends the functionality of the main menu by adding an extra item that serves as a portal to the news section. The news management system allows users to attach images to their news posts, enriching the content with visual elements. This feature not only provides a more engaging user experience but also promotes better content organization and presentation. Furthermore, the module includes a commenting system for news posts. This feature fosters interaction between users, allowing them to express their thoughts and opinions on the news. In addition, the module introduces a 'like' functionality for news posts. This allows users to express their approval or interest in a news post, providing another layer of engagement. Finally, the module provides the ability to schedule news posts for future dates. This feature allows users to plan their news content ahead of time, ensuring that their news is always timely and relevant. 

<a name="features"></a>
## Features

* Add news articles with rich formatting
* Attach images to news posts
* Comment on news articles
* Like news articles and comments
* Schedule news posts for future dates
* Pin important news to highlight them
* Categorize news with custom categories
* Track views for each news article
* Display popular news based on view count
* Fully localized in multiple languages (English, Russian, German, French, Spanish, Dutch, Icelandic, Polish, Italian, Danish, Swedish, Czech)
* Admin panel for managing news and categories

<a name="screenshots"></a>
## Screenshots

Screenshot of menu
<p align="center"><img src="docs/menu.JPG" alt="Screenshot of menu" align="center" width="80%"></p>

Screenshot of the news list with images
<p align="center"><img src="docs/news_page.JPG" alt="Screenshot of the news list with images" align="center" width="85%"></p>

Screenshot with comments to the news and reactions to them
<p align="center"><img src="docs/comments.JPG" alt="Screenshot with comments to the news and reactions to them" align="center" width="85%"></p>

Screenshot of the news addition page
<p align="center"><img src="docs/add_news.JPG" alt="Screenshot of the news addition page" align="center" width="85%"></p>

<a name="requirements"></a>
## Requirements

This module requires **webtrees** version 2.1 or later.
This module has the same requirements as [webtrees#system-requirements](https://github.com/fisharebest/webtrees#system-requirements).

This module was tested with **webtrees** 2.2.1 version and all standarts themes.

<a name="installation"></a>
## Installation

This section documents installation instructions for this module.

1. Download the [latest release](https://github.com/tywed/news-menu/releases/latest).
2. Unzip the package into your `webtrees/modules_v4` directory of your web server.
3. Login to **webtrees** as administrator, go to <span class="pointer">Control Panel/Modules/Genealogy/Menus</span>,
   and find the module. It will be called "News". Check if it has a tick for "Enabled".
4. Finally, click SAVE, to complete the configuration.

<a name="upgrade"></a>
## Upgrade

To update simply replace the `news-menu`
files with the new ones from the latest release.

<a name="support"></a>
## Support

<span style="font-weight: bold;">Issues: </span>you can report errors raising an issue in this GitHub repository.

<span style="font-weight: bold;">Forum: </span>general webtrees support can be found at the [webtrees forum](http://www.webtrees.net/)

<a name="license"></a>
## License

* Copyright © 2023 Tywed 
* Derived from **webtrees** - © 2022 webtrees development team.

* * *
