function Table (table) {

    /**
     * Modules
     */
    var root, header, columns, sizer;

    root = e('.table', [
        header = e('.header', [
            e('span.database', [ table.database ]),
            e('span.name', [ table.name ])
        ]),

        columns = e('.columns',
            _.map(table.columns, function (column) {
                return column.element = Column(column);
            })
        ),

        sizer = e('img.sizer', { src: 'img/pmd/sizer.png' })
    ]);

    /**
     * Private variables
     */
    var mouseX, mouseY, rootW, rootH, rootX, rootY;

    var dragging = false, sizing = false;

    var MIN_WIDTH;

    /**
     * When the element was selected
     */
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

        return prevent(event);
    });

    /**
     * Start dragging or sizing
     */
    $(header).on('mousedown', function () {
        dragging = true;
    });

    columns.on('mousedown', function () {
        dragging = true;
    });

    sizer.on('mousedown', function () {
        sizing = true;
    });

    /**
     * Dragging or sizing
     */
    global.on('mousemove', function (event) {
        
        if (!dragging && !sizing) {
            return;
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
        return prevent(event);

    });

    /**
     * Stop dragging or sizing
     */
    global.on('mouseup', function (event) {
        if (dragging || sizing) {
            sizing = dragging = false;
            root.trigger('end', { type: 'table', data: table, element: root });
            return prevent(event);
        }
    });

    /**
     * When the element is ready for DOM actions
     */
    root.on('ready', function () {
        
        header.css('display', 'inline-block');
        columns.css('display', 'inline-block').children().css('display', 'inline-block');

        MIN_WIDTH = Math.max(header.outerWidth(), columns.outerWidth()) + 1;
        
        header.css('display', '');
        columns.css('display', '').children().css('display', '');
    });

    /**
     * Public methods
     */
    root.extend({

        moveTo: function (x, y) {
            root.css({
                left: x + 'px',
                top: y + 'px'
            });
            update();
        },

        resize: function (w) {
            if (w < MIN_WIDTH) {
                w = MIN_WIDTH;
            }
            root.css('width', w + 'px');
            update();
        }
    });

    /**
     * Trigger the update event
     */
    function update () {
        root.trigger('moved', {
            type: 'table',
            data: table,
            element: root
        });
    }

    return root;
}