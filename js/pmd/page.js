/*page.relations = [{
    from: ['some-database-name', 'some-table-name', 'some-column-name'],
    to: ['some-database-name', 'some-table-name-2', 'some-column-name-2'],
    on_del: 'cascade',
    on_upd: 'cascade'
}]*/

function Page (page) {

    var root;

    var tableMap = {};

    root = e('#designer', [
        
        e('#header', [ e('#title', [ page.name ]) ]),

        e('#relations', _.map(page.relations, function (relation) {
            return relation.element = Relation(relation);
        })).on('update', function (e, event) {
            console.log(event);
        }),

        e('#databases', _.map(page.databases, function (database) {

            var databaseName = database.name;

            return e('.database', _.map(database.tables, function (table) {
                return (tableMap[databaseName + '.' + table.name] = table).element = Table(table);
            })).on('moved', function (e, event) {
                _.map(page.relations, function (relation) {
                    relation.element.trigger('update', event);
                });
            });
        }))
    ]);

    root.on('ready', function () {
        console.log(this);
    });

    return root;
}