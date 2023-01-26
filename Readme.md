# Smash Hit Lab Website

This is the source code and main layout for the Smash Hit Lab official website, mod database and news feed.

## Architecture

Unfortunately I was too stupid to realise that planning things out was probably a good idea for the long term. So I am already planning some progressive refactoring.

### Database

The database is essentially just flat file object oriented database organised into:

```
data
---> db
     ->
       [table/db #1]
       [table/db #2]
       ...
       ------------>
                    database file entries
```

I choose this because (a) it's very nice and simple and (b) it works well with any hosting provivder.

However this does have some drawbacks. For example, I can't easily do queries at the moment because not only do we not have index data, we also don't have any nice way to do them!

### Structure

The basic structure is modelled so that you can just place the files into a 000webhost instance, which is what I am using for testing.

The final structure might change depending on what hosting provider we switch to.
