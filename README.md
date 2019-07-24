# Package model-relationship
This is package that eases to manage model relationships automatically handling them by trait

# Usage
All you need to do is to use **Relationship** trait to your model. All create and update methods will be substituted by custom trait action that handles relations.

> Remember to add ```@relation``` annotation above all your relation methods

# How it works

When create or update action is triggered trait method automatically discover relations in provided attributes and manage to handle them acording to relation type.

### Handled relation types by now

   - BelongsTo
   - HasOne
   - HasMany
   - BelongsToMany
   
   
### Handling BelongsToMany relations

If you are dealing with `BelongsToMany` relationship and it is convinient to remove or add only few records you can pass relation with postfix like in this example

* add clients with provided data
```angular2
{
    "client_attach" : [ 
        {'id': 'int'}
    ],
}
   ```
* remove client with provided data
```
{
    "client_detach" : [
        {'id': 'int'}
    ]
}
```

Package is still under construction
