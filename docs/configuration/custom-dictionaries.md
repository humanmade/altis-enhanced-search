# Custom User Dictionaries

The search module supports 3 types of custom dictionaries for helping to tune search results for your content. These are synonyms, stop words and custom text analysis for Japanese.

In the website admin, you can upload text files (`.txt` only) and/or manually enter them on the [Search Configuration](admin://admin.php?page=search-config) screen.

Altis includes support for a Japanese user dictionary to be used by the [kuromoji tokenizer](https://www.elastic.co/guide/en/elasticsearch/plugins/6.3/analysis-kuromoji-tokenizer.html) that splits words up during analysis. Synonyms and stop words are "filters" so multiple files (uploaded and manually entered) can be used for those.

User dictionaries can be configured for [the entire network](admin://network/admin.php?page=search-config) (provided the subsites match the primary site language) or at an individual site level.

Note that for the user dictionary, manual entries will override any uploaded files. So make sure to use one or the other, not both, unlike what you can do with synonyms and stop words.

By default, Altis uses inline index settings to include all of these dictionaries, however, it is recommended to turn this feature off in case uploaded files are bigger than 100KB. This helps to improve the performance and ES cluster sizes (see below). Altis will notify you if your file size exceeds the recommended limit within the configuration page.

**Note**: If you do not use inline index settings you will need to manually reindex your content after making changes to synonyms, stopwords or the Japanese user dictionary.

```json
{
    "extra": {
        "altis": {
            "modules": {
                "search": {
                    "inline-index-settings": false
                }
            }
        }
    }
}
```

## Updating User Dictionaries

After updating custom dictionaries you will need to [reindex your content](../reindexing-content.md) unless you are on Elasticsearch version 7.8 or higher.

Elasticsearch 7.8+ usage is still experimental. Please contact support if you wish to try upgrading.

## Synonyms
For better search relevance and results, a dictionary of synonyms can be very useful in the following cases:

- There are a lot of acronyms or other contractions in your content
- There are common misspellings of search terms
- There are users from different countries who may use different words for the same thing
- There are sub-categories of content that should match a more general search term

For example, you may want search results for the phrase "Checking Accounts" to match content that is titled "Current Accounts" or for common acronyms like "GDP" to match "Gross Domestic Product".

Additionally, to cater for variations in language, you could create synonyms for American English e.g. "sneakers" to British English e.g. "trainers".

Comma-separated lists of words are treated as equivalent. You should have one list of synonyms per line.

```
sneakers, trainers, footwear, shoes
foozball, foosball, table football
CPU, central processing unit
```

Comma-separated words or phrases followed by "=>" will be treated the same as comma-separated words or phrases to the right of the "=>" operator but not the other way around.

```
i-pod, i pod => ipod
tent => bivouac, teepee
sea biscuit, sea biscit => seabiscuit
```

In the above example, a search for "tent" will match content containing "bivouac" or "teepee" but a search for "teepee" will only return results containing the word "teepee" specifically.


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

A user dictionary provides a way to control how words are broken up when searching. If there are compound words or phrases specific to your site, such as the names of imaginary places, or authors and celebrities that users may search for, they can be specified here to increase search relevancy.

The syntax for the provided text file should follow the Comma-Separated Values (CSV) format:

```csv
text,tokens,readings,part-of-speech
```

1. `text` is the compound word or phrase that appears in your content, such as a name
2. `tokens` must contain the same text again with spaces added between each word
3. `readings` must contain the same text as `tokens` with any kanji replaced by katakana. This describes the pronunciation of the tokens.
4. `part-of-speech` defines what the text is, for example a noun or verb

By default the text "東京スカイツリー" would be broken up into "東京", "スカイ" and "ツリ". The example below changes this behavior so that the text is treated as a custom noun:

```csv
東京スカイツリー,東京 スカイツリー,トウキョウ スカイツリー,名詞
```

1. The `text` is "東京スカイツリー"
2. The `tokens` are "東京" and "スカイツリー"
3. The `readings` are "トウキョウ" and "スカイツリー"
4. The `part-of-speech` is "名詞"

The available parts of speech are:

- 名詞
- 固有名詞
- 形式名詞
- 動詞
- 助動詞
- 複合動詞
- 形容詞
- 形容動詞
- 連体詞
- 前置詞
- 辞書形
- 普通形
- 丁寧形
- 活用形
- 終止形
- 命令形
- 使役形
- 四段活用
- 条件形
- 助数詞
- 対比
- 否定
- 強調
- 簡略化
- 筆者
- 確認
- 説明
- 伝聞
- 目的
- 不可避
- 敬語
- 副助詞
