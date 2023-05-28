# What I can explain about how the site code works

Firstly, the source code is located alongside the `db` (database) folder in the `data` folder. Don't ask me why exactly, but it was originally intended to make it harder to access the source code via some kind of undocumented webserver feature, like adding an `s` to the end of a php file to get the source code.

The site was targeted to PHP 8.0, but seems to run fine when I configure the server to use 8.2 instead. It doesn't really use any modern features of PHP, I don't think they would have been overly helpful.

Currently the site code is about 15% (or less probably) though a transition to using a bit of a more sustainable architecture. The idea with the new stuff is to have an endpoint manager that can take an anonymous function which takes a page object. This page object then has the properties to get data, auto-sanitise it, send data back etc.

The old stuff is a switch-case and calling whatever function is needed directly. O_O

## Database

Relevant files: `database.php`

The database is basically some flat JSON files organised into folders. It essentially works like an unindexed Document Store Database. The database class takes the database folder to open upon initialising it. Then you can use methods to read, write, delete etc. entries into the database identified by a filename.

You can do things like:

```php
$db = new Database("my_db");
$data = $db->load("some_entry");
$data->something = "that thing";
$db->save("some_entry", $data);
```

## User accounts

Relevant files: `user.php` (most code), `auth.php` (`auth-login` and `auth-register`), `userblock.php` (user blocking)

The user accounts system was the first and has the most code cruft. It would need work to make it more maintainable, and some of it needs some better security audits. Hopefully I didn't do anything too awful for security though.

Another issue with it is that users don't have proper ids, instead they are identified by their handle. I thought about fixing this but I'm not sure it matters for a site of this size.

The password reset system needs a serious security audit, it was made in 30 minutes. I know it uses non-constant-time string comapres of hashes, for example, and that's not really a good sign. I had intended to fix this very soon but I'm just not doing that now.

Did I mention that users have too many database feilds? Really account logins should probably be split from actual users in the database.

Also, its worth noting that the `logout` action shloud be moved to `auth-logout` and placed in `auth.php` after being updated to be more inline with the other actions.

## Discussions

Relevant files: `discussion.php`

Discussions are actually quite nice, they essentially work by just posting to some unified endpoints for all types of discussions. They are some of the only parts of the site that use AJAX and have a somewhat nice API.

Dicussion bodies use markdown and can be formatted.

Other than this, there is not much to say.

## News and mod pages

Relevant files: `mod.php`, `news.php`

These are actually very basic. Essentially just plain articles that people can edit. They use a special version of the database called RevisionDB which automatically takes care of things like handling revisions.

Both article and mod page bodies use markdown, specifically the Parsedown library to render it to HTML.

I was hoping to make the transition to using an AJAX-based editor for mod pages. It is actually available, but it doesn't work very well when mixed with the markdown parser.

News article have a special editor that is nicer for editing articles. They can also be private (and are by default) so that things and be drafted before they are written.

## Mod services

Relevant files: `mod_services.php`, `ads.php` (in `public_html`)

Mod services use 5 base64 chars for IDs so that the ids fit into the `libsmashhit.so` binary. This doesn't seem like a lot until you notice that 64<sup>5</sup> is 1 073 741 824.

The actual request and response for the ad server is documented in the smashing tech wiki and also in the smash hit lab documentation.

Also yes I convert the files into base64 and store them in json. It's stupid but it works :)

If you want to implement the stats backend server like I wanted to for mod services, you probably need to use some kind of real database like mysql.

Other than that I'm not sure what else to do for mod services aside from an in-browser patching tool.

## Admin dashboard and features

There are some admin forms. They are really boring, but some do need some work done.

## Forms and pages

The `Page` class (`page.php`) takes care of basically everything related to the page, like rendering it out, sending headers, and even getting form data. Example:

```php
// assume we already have Page object
$page->add("<i>HELLO WORLD!!!</i>");
$page->heading(1, "This is a heading!");
$page->para($page->get("my-test-value")); // get my-test-value from GET/POST request
```

The `Form` class (`form.php`) allows you to construct forms. Example:

```php
$form = new Form("./?a=my-test-thing"); // url to post to
$form->textbox("my-test-value", "My Text Box", "This is a description for this textbox.", "default value");
$form->submit("Submit form"); // submit button
$page->add($form); // add the form to the page
```

Anything before the page class was made uses echo directly, sometimes using the things in `templates.php` for common constructions.

## Endpoint manager

The endpoint manager takes care of registering endpoints and also serving the user the endpoint that they would like to access.

The endpoint manager has only one real important function: `add`. For example:

```php
$function_to_handle_my_test_thing = function (Page $page) {
	$page->add("Hello, world!");
	$page->send(); // will automatically happen even if not specified
};

$gEndMan->add("my-test-thing", $function_to_handle_my_test_thing);
```

## Common patterns for objects

Usually if an object that has a DB representation doesn't exist, it will be automatically created. This is usually done by opening the db, checking if it has the given object and either loading if it exists or initialising a default one if it doesn't.

Due to hosting limits on inodes, sometimes things that should have been their own tables/databases/colections are instead inserted directly into other objects. For example discussions has its comments in the actual file for the given discussion.

## Other code notes

All files are included from `main.php` instead of having `require_once`'s everywhere. IMO it's better style and less work to maintain.

## Live chat and cancelled features

Live chat was going to be a feature, but after mutlipule starts to implementing it, it never really worked. You can delete `chat.php` safely.

I was going to have a unified system for managing larger files (site storage) and who can access them. This is the `storage.php`.

It seems like there is actually a file that would have seperated accounts and users, but it seems very unfinished. It's probably okay to delete `account.php`.

## Suggestions

It might be considered to move off of the properiary site codebase and just base on something like wordpress or even medawiki with addons instead.

Maybe switch to using something like Discord OAuth2 instead of the builtin auth.
