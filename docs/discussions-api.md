# Discussions API

### `GET` `/?a=discussion_poll` `&id=<discussion-id>` `&index=<lower-bound>`

* `<discussion-id>`: The ID of the discussion to poll.
* `<lower-bound>`: The index of the first commnent that should be retrived. If you just want to load the newest comments, make sure index is near the latest.

Responds with a format like this:

```json
{
  "anything": true,
  "comments": [
    {
      "author": "knot126",
      "body": "<p>This is a test.<\\/p>",
      "created": 1679725017,
      "updated": 1679725017,
      "hidden": false,
      "index": 0,
      "display": "Knot the Dragon",
      "image": "https:\\/\\/www.gravatar.com\\/avatar\\/6633e4dedc84d76e34d3d711d9481b01?s=300&d=identicon",
      "actions": [
        "reply",
        "hide"
      ]
    },
    {
      "author": "knot126",
      "body": "<p>lmao<\\/p>",
      "created": 1680049074,
      "updated": 1680049074,
      "hidden": false,
      "index": 1,
      "display": "Knot the Dragon",
      "image": "https:\\/\\/www.gravatar.com\\/avatar\\/6633e4dedc84d76e34d3d711d9481b01?s=300&d=identicon",
      "actions": [
        "reply",
        "hide"
      ]
    }
  ],
  "actor": "knot126",
  "next_sak": "4a0c453d844baccaaac9701a302459e2d8e8574dc83fc77c33be6af03763e640"
}
```

* `anything`: True if `len(comments) > 0`, false otherwise (deprecated)
* `comments`: List of:
  * `author`: Handle of the user who made the comment
  * `body`: HTML rendered body of the comment
  * `created`: Unix timestamp the comment was created
  * `updated`: Unix timestamp the comment was edited (note: editing comments does not currently work)
  * `hidden`: If the comment is hidden (normally `false` unless an admin enabled hidden comments)
  * `index`: The real index of the comment
  * `display`: The display name of the user who posted the comment
  * `image`: The image URL of the person who posted the comment
  * `actions`: What actions should be available for this comment
* `actor`: The handle of the person who made the poll request
* `next_sak`: The next action key for the user
