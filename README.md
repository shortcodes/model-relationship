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
   
Package is still under construction
