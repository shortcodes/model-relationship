# Package model-relationship
This is package that eases to manage model relationships automatically handling them by trait

# Usage
All you need to do is to use **Relationship** trait to your model. Observer that is booted when You using a trait handle all relations:

> Remember to add ```@relation``` annotation above all your relation methods

# How it works

When create or update action is triggered trait method automatically discovers relations in provided attributes and manage to handle them according to relation type.

### Handled relation types

   - HasOne
   - BelongsTo
   - HasMany
   - BelongsToMany

### Prequisites

Lets assume we have an example Article model with relations

    class Articel extends Model{
    
            /**
             * @relation
             */
            public function author()
            {
                return $this->belongsTo(Author::class);
            }
        
            /**
             * @relation
             */
            public function referrals()
            {
                return $this->hasMany(Referral::class);
            }
        
            /**
             * @relation
             */
            public function image()
            {
                return $this->hasOne(Image::class);
            }
        
            /**
             * @relation
             */
            public function tags()
            {
                return $this->belongsToMany(Tag::class);
            }
    
    }

### HasOne

While adding `HasOne` relation there is few ways to do that

Providing image_id

    Article::create([...$someArticleAttributes,'image_id'=>$image_id]);

Providing image object with ID (all other attributes of image are being ignored)

    Article::create([...$someArticleAttributes,'image'=> ['id'=>$image_id]]);

Providing image object without ID (image will be created and binded to article)

    Article::create([...$someArticleAttributes,'image'=> ['url'=>'https://example.url','title'=>'Example title']]);

### BelongsTo

While adding `BelongsTo` relation you can provide either `object_id` or object like notation `object['id']`

Providing author_id

    Article::create([...$someArticleAttributes,'author_id'=>$author_id]);

Providing author object with ID (all other attributes of author are being ignored)

    Article::create([...$someArticleAttributes,'author'=> ['id'=>$author_id]]);

### HasMany

While adding `HasMany` relation you should provide a collection of related objects 

    Article::create([...$someArticleAttributes,'referrals'=> [
        [
            'url'=>'https://example.url'
        ],
        [
            'url'=>'https://example.url'
        ],
        [
            'url'=>'https://example.url'
        ]
    ]);
    
While updating `HasMany` relation in `Article` you should provide a similar collection of related objects 

    $article->update(['referrals'=> [
        [
            'id'=>1,
            'url'=>'https://example.url'
        ],
        [
            'id'=>2,
            'url'=>'https://example.url'
        ],
        [
            'url'=>'https://example.url'
        ]
    ]);
    
If related model attributes contain `id` it will  be updated. If it does not - it will be created and binded.
>IMPORTANT! In case above all referrals that are not in referrals attributes array will be deleted.

##### Adding new object to relation.

If you do not want to pass all present referrals ids to referrals array (so they would not be deleted) you can use postfix notation

    $article->update(['referrals_add'=> [
        [
            'url'=>'https://example.url'
        ]
    ]);

Old referrals associated with article won't be affected.

> Note that if you provide already existing model (with id) to referrals_add array it will be attached which is described below.

##### Removing new object to relation.

If you want to remove only one or few referrals without passing ids to referrals array (so they would not be deleted) you can use another postfix notation

    $article->update(['referrals_delete'=> [
        [
            'id'=>1
        ]
    ]);

Old referrals associated with article won't be affected.

##### Attach already existing object to relation.

If you want to associate existing models like referrals without assigned relation id (null) you can use postfix notation `_attach`

    $article->update(['referrals_attach'=> [
        [
            'id'=>1
        ]
    ]);

Existing referrals will be associated with article.

##### Detach object from relation.

If you want to disassociate existing models like referrals (set  foreign key to null) you can use postfix notation `_detach`

    $article->update(['referrals_detach'=> [
        [
            'id'=>1
        ]
    ]);

Ids provided in referrals_detach array will be disassociated from article.

> Remember that this feature require setting foreign key of relation to nullable in database

> Remember that association will be performed even if related object is associated with another model.

##### Ordering Relation objects

Sometimes you need to sort `hasMany` relations by `position`. In that case all you need to do is to simply pass referrals array with ids in order you want. This can be possible if related object (in this case referrals) have to contain `position` column in database.

    $article->update(['referrals'=> [
        [
            'id'=>5
        ],
        [
            'id'=>3
        ],
        [
            'id'=>2
        ],
    ]);

> Remember that absent ids will be removed

### BelongsToMany

While adding `BelongsToMany` relation you should provide a collection of related objects ids

     Article::create([...$someArticleAttributes,'tags'=> [
            [
                'id'=>5
            ],
            [
                'id'=>3
            ],
            [
                'id'=>2
            ],
            [
                'id'=>1
            ],
        ]);

Above tags will be associated by `many-to-many` relation with Article.

If you want to pass additional data to pivot table simply add it to attributes table

     Article::create([...$someArticleAttributes,'tags'=> [
            [
                'id'=>5,
                'weight'=>1
            ],
            [
                'id'=>3,
                'weight'=>1
            ],
            [
                'id'=>2,
                'weight'=>3
            ],
            [
                'id'=>1,
                'weight'=>4
            ],
        ]);

> Since Laravel `belongsToMany` relation use `force` option to fill pivot table remember not to pass any fields that are not in database

##### Adding new object to relation.

You have also possibility to add one or few objects to belongsToMany relation using postfixes similar to `hasMany` relation with `_attach` postfix

    $article->update(['tags_attach'=> [
        [
            'id'=>1
        ],
        [
            'id'=>2
        ]
    ]);

All tags absent in array associated with article won't be affected.

##### Removing new object to relation.

Same thing works for `_detach` prefix when you want ro remove association

    $article->update(['tags_detach'=> [
        [
            'id'=>1
        ],
        [
            'id'=>2
        ]
    ]);

All tags absent in array associated with article won't be affected.

##### Ordering Relation objects

Simimilar to `hasMany` sometimes you need to sort `belongsToMany` associated relations by `position`. In that case all you need to do is to simply pass `tags` array with ids in order you want. This can be possible when there is `position` column in database on pivot table.

    $article->update(['tags'=> [
        [
            'id'=>5
        ],
        [
            'id'=>3
        ],
        [
            'id'=>2
        ],
    ]);

> Remember that absent ids will be removed

Package is still under construction
