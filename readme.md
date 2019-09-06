# Flexible Views

## Installation

### composer.json

Add this under the repositories key:

```
{
    "type": "package",
    "package": {
        "name": "efork/flexible_views",
        "version": "1.0",
        "type":"drupal-module",
        "source": {
            "url": "https://github.com/Daveiano/flexible_views.git",
            "type": "git",
            "reference": "8.x-1.x"
        }
    }
}
```

### Command line

`composer require efork/cleanimal`


### Other

Use this after color settings have changed (new color added in theme for coniguration):

`drush cdel color.theme.cleanimal`
