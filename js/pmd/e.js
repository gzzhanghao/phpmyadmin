function e (name, attrs, children) {
    
    if (_.isObject(name)) {
        children = attrs;
        attrs = name;
        name = '';
    }
    if (_.isArray(attrs)) {
        children = attrs;
        attrs = {};
    }
    attrs = attrs || {};

    name = name.replace(/[\.#][^\.#]+/g, function (attr) {
        if ('#' === attr.charAt(0)) {
            attrs.id = attr.slice(1);
        } else {
            attrs.class = attrs.class || '';
            attrs.class = (attrs.class + ' ' + attr.slice(1)).replace(/^\s+|\s+$/, '');
        }
        return '';
    });
    if (name.length === 0) {
        name = 'div';
    }

    return $(document.createElement(name || 'div')).attr(attrs).append(children);
}