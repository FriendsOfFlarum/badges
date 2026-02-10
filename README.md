# Badges by FriendsOfFlarum

![License](https://img.shields.io/badge/license-MIT-blue.svg) [![Latest Stable Version](https://img.shields.io/packagist/v/fof/badges.svg)](https://packagist.org/packages/fof/badges) [![Total Downloads](https://img.shields.io/packagist/dt/fof/badges.svg)](https://packagist.org/packages/fof/badges) [![OpenCollective](https://img.shields.io/badge/opencollective-fof-blue.svg)](https://opencollective.com/fof/donate)

A [Flarum](https://flarum.org/) extension. A comprehensive badge and achievement system with conditional triggers, automatic awarding, and manual assignment.

- Create badges with custom icons, colors, and descriptions
- Organize badges into categories
- Automatic awarding based on configurable trigger conditions (post count, likes, member days, etc.)
- AND/OR logic, date ranges, and tag filtering for triggers
- Manual badge assignment by moderators
- Notifications and automatic group assignment on badge earned
- Badge list page, profile tab, rarity tiers, and user card integration
- Built-in integration with many FoF and Flarum extensions

![](https://i.ibb.co/cSw0DdJF/image.png)

## Installation

Install manually with composer:

```sh
composer require fof/badges:"*"
```

## Updating

```sh
composer update fof/badges
php flarum migrate
php flarum cache:clear
```

## Optional Dependencies

This extension integrates with the following extensions to provide additional badge metrics:

- [`flarum/likes`](https://github.com/flarum/likes) — Likes Received / Given
- [`flarum/tags`](https://github.com/flarum/tags) — Posts in Tag
- [`flarum/nicknames`](https://github.com/flarum/nicknames) — Has Nickname
- [`fof/user-bio`](https://github.com/FriendsOfFlarum/user-bio) — Has Bio
- [`fof/best-answer`](https://github.com/FriendsOfFlarum/best-answer) — Best Answers Received
- [`fof/upload`](https://github.com/FriendsOfFlarum/upload) — Files Uploaded
- [`fof/polls`](https://github.com/FriendsOfFlarum/polls) — Polls Created / Voted
- [`fof/byobu`](https://github.com/FriendsOfFlarum/byobu) — Private Discussions Created
- [`fof/reactions`](https://github.com/FriendsOfFlarum/reactions) — Reactions Received / Given
- [`fof/gamification`](https://github.com/FriendsOfFlarum/gamification) — Upvotes / Downvotes Received / Given

## Links

[![OpenCollective](https://img.shields.io/badge/donate-friendsofflarum-44AEE5?style=for-the-badge&logo=open-collective)](https://opencollective.com/fof/donate)

- [Packagist](https://packagist.org/packages/fof/badges)
- [GitHub](https://github.com/FriendsOfFlarum/badges)
- [Discuss](https://discuss.flarum.org/d/ID)
- [Issues](https://github.com/FriendsOfFlarum/badges/issues)

An extension by [FriendsOfFlarum](https://github.com/FriendsOfFlarum).
