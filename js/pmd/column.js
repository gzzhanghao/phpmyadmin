function Column (column) {
    
    var root;

    root = e('.column', [
        e('span.name', [ column.name ]),
        e('span.type', [ column.type ])
    ]);

    root.click(function (event) {
        root.trigger('selected', { type: 'column', data: column, element: root });
        return prevent(event);
    });

    return root;
}