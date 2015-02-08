/*page.relations = [{
    from: ['some-database-name', 'some-table-name', 'some-column-name'],
    to: ['some-database-name', 'some-table-name-2', 'some-column-name-2'],
    on_del: 'cascade',
    on_upd: 'cascade'
}]*/

function Page (page) {

    var root, relations, databases;

    root = e('#designer', [
        
        e('#header', [ e('#title', [ page.name ]) ]),

        relations = e('#relations',
            _.map(page.relations, function (relation) {
                return relation.element = Relation(relation);
            })
        ),

        e('#databases', _.map(page.databases, function (database) {
            return e('.database', 
                _.map(database.tables, function (table) {
                    return table.element = Table(table);
                })
            ).on('moved selected', function (e, event) {
                event.database = database;
            });
        }))
    ]);

    relations.on('update', function (e, event) {
        // body...
    });

    root.on('moved', function (e, event) {
        console.log(event);
    })

    root.on('selected', function (e, event) {
        console.log(event);
    });

    return root;
}