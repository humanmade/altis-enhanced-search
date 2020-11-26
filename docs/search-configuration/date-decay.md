# Date Decay

The ordering of the results of an Elasticsearch query is done by comparing the weighting score for each post. This score is calculated as a base score:

```
 [ best exact match relevance ] + [ best fuzzy match relevance ]
```

... modified by a decay function score representing the staleness of the post.

ElasticPress's default settings are to use a function which returns a float from 0-1, where 1 represents a post updated in the past week, and the score approaches zero quite rapidly - a post published two months ago will be around .15).

The default setting for "boost mode" - how the decay function is used to modify the base score - is "sum", meaning that this 0-1 value is added to the base score. Since the base scores for exact matches of moderately common search terms (ie terms that appear in 2-3% of posts on a site) can be in the realm of 100-150, adding this decay score has very little impact beyond guaranteeing a consistant order for posts with otherwise identical relevance scores.

For sites that want to prioritize timeliness of results for search queries, using a "boost mode" of "multiply" may be desirable, so that recency of content has a higher impact on search results. Using this, the decay score is a fractional multiplier which ensures that more recent results are prioritized.

_(If you want to go deeper into the equations under the hood, the Elasticsearch documentation about [supported decay functions](https://www.elastic.co/guide/en/elasticsearch/reference/7.7/query-dsl-function-score-query.html) is a good place to dig in.)_

Altis Enhanced Search allows for overriding these default values by specifying the values you want to use in the composer.json config object:

```
{
    "extra": {
        "altis": {
            "modules": {
                "search": {
                    "date-decay": {
                        "offset": "30d",
                        "scale": "30d",
                        "decay": 0.9,
                        "boost_mode": "multiply"
                    }
                }
            }
        }
    }
}
```

This configuration creates a date decay algorithm where posts from the past 30 days ("offset") are considered equally current, and for every 30 days in the past ("scale") posts are considered to lose 10% of their relevancy ("decay"). A post from a year ago is weighted about 30% as highly (0.9^12 = .2824) as one from the past week.

A useful correlation by which to understand this score is that exact matches are about 4x more impactful on a post's score as fuzzy matches. Deciding at what age a post which exactly matches the search terms presented is stale enough that it should not outweigh a current post in search results can help to derive the values to use for the decay function.
