var global;

function prevent (event) {
    if (event.preventDefault) {
        event.preventDefault();
    } else {
        event.returnValue = false;
    }
    if (event.stopPropagation) {
        event.stopPropagation();
    }
    return false;
}

$(document).ready(function () {

    var layer = 0;

    global = $('#designer-container');

    var page = Page({
        name: 'helloworld',
        databases: [{
            name: 'some-database-name',
            tables: [{
                name: 'some-table-name',
                columns: [{
                    name: 'some-column-name',
                    type: 'int',
                    is_primary: true
                }]
            }, {
                name: 'some-table-name-2',
                columns: [{
                    name: 'some-column-name-2',
                    type: 'int',
                    index: [ 'index' ]
                }]
            }]
        }],
        relations: [{
            from: ['some-database-name', 'some-table-name', 'some-column-name'],
            to: ['some-database-name', 'some-table-name-2', 'some-column-name-2'],
            on_del: 'cascade',
            on_upd: 'cascade'
        }]
    });

    page.on('selected', function (event, selected) {
        $(selected.element).css('z-index', layer++);
    })

    page.appendTo(global);

    page.find('*').each(function () {
        $(this).triggerHandler('ready');
    });
});