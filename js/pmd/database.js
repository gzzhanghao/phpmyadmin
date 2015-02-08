function Database (database) {

    var root, tables;

    var tableMap = {};

    root = e('.database', [
        tables = e('.tables', _.map(database.tables, function (table) {
            return (tableMap[table.name] = table).element = Table(table);
        }))
    ]);

    root.on('ready', function () {
        _.each(database.tables, function (table) {
            table.element.triggerHandler('ready');
        });
    });

    return root;
}