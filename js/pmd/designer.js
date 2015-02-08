var global = $(document);

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

    name = name.replace(/[\.#][^\.#]+/g, function (attr) {
        if ('#' === attr.charAt(0)) {
            attrs.id = attr.slice(1);
        } else {
            attrs.class = attrs.class || '';
            attrs.class = (attrs.class + ' ' + attr.slice(1)).replace(/^\s+|\s+$/, '');
        }
        return '';
    });

    return $(document.createElement(name || 'div')).attr(attrs).append(children);
}

function preventDefault (event) {
    if (event.preventDefault) {
        event.preventDefault();
    } else {
        event.returnValue = false;
    }
    if (event.stopPropagation) {
        event.stopPropagation();
    }
    return false
}

function Page (page) {

    var root, header, relations, tables;

    root = e('#designer', [
        
        header = Header(page),

        relations = e('#relations', _.map(page.relations, function (relation) {
            return relation.element = Relation(relation);
        })),

        tables = e('#tables', _.map(page.tables, function (table) {
            return table.element = Table(table);
        }))
    ]);

    tables.on('selected', function (event, selected) {
        console.log(event, selected);
    }).on('moved', function (event, position) {
        console.log(event, position);
    });

    relations.on('delete', function (event, relation) {
        // body...
    });

    return root;
}

function Header (page) {
    
    var root;

    root = e('#header', [
        e('#title', [ page.name ])
    ]);

    return root;
}

function Relation (relation) {
    
    var root;

    root = e('.relation');

    return root;
}

function Table (table) {
    
    var root, header, columns, sizer;

    root = e('.table', [
        header = e('.header', [
            e('span.database', [ table.database ]),
            e('span.name', [ table.name ])
        ]),

        columns = e('.columns', _.map(table.columns, function (column) {
            return column.element = Column(column);
        })),

        sizer = e('img.sizer', { src: 'img/pmd/sizer.png' })
    ]);

    var mouseX, mouseY, rootW, rootH, rootX, rootY;

    var dragging = false, sizing = false;

    var MIN_WIDTH;

    root.on('mousedown', function (event) {
        
        // Saving current state
        rootX = parseFloat(root.css('left'));
        rootY = parseFloat(root.css('top'));
        rootW = root.width();
        rootH = root.height();

        // Getting mouse relative position
        mouseX = event.pageX;
        mouseY = event.pageY;

        // Trigger the selected event
        root.trigger('selected', {
            event: event,
            type: 'table',
            data: table,
            element: root
        });

        return preventDefault(event);
    })

    .extend({

        moveTo: function (x, y) {
            root.css({
                left: x + 'px',
                top: y + 'px'
            });
        },

        resize: function (w) {
            if (w < MIN_WIDTH) {
                w = MIN_WIDTH;
            }
            root.css('width', w + 'px');
        }
    });

    $(header).on('mousedown', function () {
        dragging = true;
    });

    columns.on('mousedown', function () {
        dragging = true;
    });

    sizer.on('mousedown', function () {
        sizing = true;
    });

    global.on('mousemove', function (event) {
        
        if (!dragging && !sizing) {
            return;
        }

        // Initialize minimal width
        if (!MIN_WIDTH) {
            MIN_WIDTH = header.css('display', 'inline-block').outerWidth();
            header.css('display', '');
        }

        // Get the new mouse relative position
        var deltaX = event.pageX - mouseX;
        var deltaY = event.pageY - mouseY;

        if (dragging) {
            root.moveTo(rootX + deltaX, rootY + deltaY);
        }

        if (sizing) {
            root.resize(rootW + deltaX);
        }

        root.trigger('moved', { type: 'table', data: table, element: root });
        return preventDefault(event);

    }).on('mouseup', function (event) {
        if (dragging || sizing) {
            sizing = dragging = false;
            root.trigger('end', { type: 'table', data: table, element: root });
            return preventDefault(event);
        }
    });

    return root;
}

function Column (column) {
    
    var root;

    root = e('.column', [
        e('span.name', [ column.name ]),
        e('span.type', [ column.type ])
    ]);

    root.click(function (event) {
        root.trigger('selected', { type: 'column', data: column, element: root });
        return preventDefault(event);
    });

    return root;
}

$(document).ready(function () {

    var layer = 0;

    global = Page({
        name: 'helloworld',
        databases: {
            db: {
                name: {
                    database: 'db',
                    name: 'name',
                    columns: {
                        column: {
                            name: 'column',
                            type: 'int',
                            is_primary: true
                        }
                    }
                },
                another: {
                    database: 'db',
                    name: 'another',
                    columns: {
                        acolumn: {
                            name: 'acolumn',
                            type: 'int'
                        }
                    }
                }
            }
        },
        relations: [{
            from: {
                database: 'db',
                table: 'name',
                column: 'column'
            },
            to: {
                database: 'db',
                table: 'another',
                column: 'acolumn'
            },
            on_del: 'CASCADE',
            on_upd: 'CASCADE'
        }]
    }).appendTo($('#designer-container').empty())

    .on('selected', function (event, selected) {
        $(selected.element).css('z-index', layer++);
    });
});