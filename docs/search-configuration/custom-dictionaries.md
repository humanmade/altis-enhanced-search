# Custom User Dictionaries

The search module supports 3 types of custom dictionaries for helping to tune search results to your content. These are synonyms, stop words and custom text analysis for Japanese.

In the website admin you can upload text files (`.txt` only) or in the case of stop words and synonyms manually enter them on the [Search Configuration](admin://admin.php?page=search-config) screen.

Note that only a file upload is supported for Japanese user dictionaries.

User dictionaries can be configured for [the entire network](admin://network/admin.php?page=search-config) (provided the subsites match the primary site language) or at an individual site level.

## Synonyms
For better search relevance and results, a dictionary of synonyms can be very useful in the following cases:

- There are a lot of acronyms or other contractions in your content
- There are invented words such as product names in the content that do not appear in the dictionary
- There are common mis-spellings of search terms
- There are users from different countries who may use different words for the same thing
- There are sub categories of content that should match a more general search term

For example, you may want search results for the phrase "Checking Accounts" to match content that is titled "Current Accounts" or for common acronyms like "GDP" to match "Gross Domestic Product".

Additionally to cater for variations in language you could create synonyms for American English e.g. "sneakers" to British English e.g. "trainers".

Comma separated lists of words are treated as equivalent. You should have one list of synonyms per line.

```
sneakers, trainers, footwear, shoes
foozball, foosball, table football
CPU, central processing unit
```

Comma separated words or phrases followed by "=>" will be treated the same as comma separated words or phrases to the right of the "=>" operator but not the other way around.

```
i-pod, i pod => ipod
tent => bivouac, teepee
sea biscuit, sea biscit => seabiscuit
```

In the above example a search for "tent" will match content containing "bivouac" or "teepee" but a search for "teepee" will only return results containing the word "teepee" specifically.


## Stop Words
Stop words are words that are ignored when analysing content and searching content. Altis uses the standard dictionary of stop words for each supported language. For example in english this includes "it", "of", "and" and so on.

The default list should be adequate in most cases.

A stop words dictionary should contain one word per line:

```
ignore
these
words
```


## Japanese User Dictionary
Your site's language must be set to Japanese to see this option.

A user dictionary provides a way to control how words are broken up when searching. If there are compound words or phrases specific to this site such as the names of authors or celebrities that users may search for they can be specified here to increase search relevancy.

The syntax for the provided text file should follow the CSV format:

```csv
text, token 1 ... token n, reading 1 ... reading n, part-of-speech tag
```

For example:

```csv
東京スカイツリー,東京 スカイツリー,トウキョウ スカイツリー,カスタム名詞
```

Will cause "東京スカイツリー" to be interpreted as "東京" and "スカイツリー" rather than "東京", "スカイ" and "ツリ".
