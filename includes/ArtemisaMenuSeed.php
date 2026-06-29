<?php
declare(strict_types=1);

/**
 * Carta Artemisa Salón de Té — Carta 2026
 * Precios en pesos chilenos (CLP)
 */
class ArtemisaMenuSeed
{
    private const COFFEE_EXTRAS = [
        ['Leche vegetal', 500],
        ['Crema batida', 500],
        ['Malvaviscos', 500],
    ];

    private const TEA_EXTRAS = [
        ['Tetera', 5000],
    ];

    private const HELADAS_EXTRAS_500 = [
        ['Crema Chantilly', 500],
        ['Frutas', 500],
        ['Crema de Maní', 500],
        ['Cono', 500],
    ];

    public static function seed(PDO $db): void
    {
        $db->exec('DELETE FROM order_items');
        $db->exec('DELETE FROM orders');
        $db->exec('DELETE FROM extras');
        $db->exec('DELETE FROM ingredients');
        $db->exec('DELETE FROM menu_items');
        $db->exec('DELETE FROM categories');

        $categories = [
            ['Cafés', 1],
            ['Variedades de té', 2],
            ['Saladas', 3],
            ['Pastelería', 4],
            ['Heladas', 5],
            ['Bebidas frías', 6],
        ];

        $stmtCat = $db->prepare('INSERT INTO categories (name, sort_order) VALUES (?, ?)');
        foreach ($categories as [$name, $order]) {
            $stmtCat->execute([$name, $order]);
        }

        self::seedCafes($db);
        self::seedTeas($db);
        self::seedSaladas($db);
        self::seedPasteleria($db);
        self::seedHeladas($db);
        self::seedBebidasFrias($db);
    }

    private static function insertItem(
        PDO $db,
        int $catId,
        string $name,
        string $desc,
        float $price,
        int $sort,
        ?float $priceDouble = null,
        array $ingredients = [],
        array $extras = []
    ): int {
        $stmt = $db->prepare(
            'INSERT INTO menu_items (category_id, name, description, price, price_double, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$catId, $name, $desc, $price, $priceDouble, $sort]);
        $itemId = (int) $db->lastInsertId();

        $stmtIng = $db->prepare('INSERT INTO ingredients (menu_item_id, name) VALUES (?, ?)');
        foreach ($ingredients as $ing) {
            $stmtIng->execute([$itemId, $ing]);
        }

        $stmtExt = $db->prepare('INSERT INTO extras (menu_item_id, name, price) VALUES (?, ?, ?)');
        foreach ($extras as [$extName, $extPrice]) {
            $stmtExt->execute([$itemId, $extName, $extPrice]);
        }

        return $itemId;
    }

    private static function seedCafes(PDO $db): void
    {
        $coffees = [
            ['ESPRESSO', 'Café de grano molido', 2200, 2500, 1],
            ['AMERICANO', 'Espresso con agua caliente', 2200, 2500, 2],
            ['LATTE', 'Espresso con leche caliente y una ligera capa de espuma', 3000, 3500, 3],
            ['CAPPUCCINO', 'Espresso, leche al vapor y espuma', 3000, 3500, 4],
            ['CAPPUCCINO VAINILLA', 'Amareto, caramelo o avellana', 3000, 3500, 5],
            ['CAPPUCCINO CLEMENTINA', 'Espresso con leche de vaca, suave y reconfortante', 3000, 3500, 6],
            ['CAPPUCCINO NUTELLA', 'Espresso, Nutella, espuma de leche', 3500, 4000, 7],
            ['CAPPUCCINO MANÍ', 'Espresso, mantequilla de maní y espuma de leche', 3000, 3500, 8],
            ['IRLANDÉS', 'Espresso más crema de whisky, leche y espuma', 3000, 3500, 9],
            ['MOCCA', 'Espresso, chocolate blanco o bitter, leche y espuma', 3000, 3500, 10],
            ['MOCCA MENTA', 'Espresso, chocolate blanco o bitter, menta, leche y espuma', 3500, 4000, 11],
            ['LATTE MACCHIATO', 'Leche con una mancha de café', 2000, 2400, 12],
            ['ESPRESSO MACCHIATO', 'Espresso con una mancha de leche', 2000, 2400, 13],
            ['CHOCOLATE CALIENTE', 'Chocolate belga y leche al vapor', 3000, 3500, 14],
            ['CAFÉ TRES LECHES', 'Espresso, leche condensada, crema y leche al vapor', 3500, 4000, 15],
        ];

        foreach ($coffees as [$name, $desc, $simple, $doble, $sort]) {
            self::insertItem($db, 1, $name, $desc, $simple, $sort, $doble, [], self::COFFEE_EXTRAS);
        }
    }

    private static function seedTeas(PDO $db): void
    {
        $teas = [
            ['TÉ FLOR GUISANTE DE MARIPOSA', 'Té de flor guisante de mariposa', 2500, 1, []],
            ['TÉ DE HOJAS', 'Azul, blanco, negro, rojo y verde', 2000, 2, [
                ['Azul', 0], ['Blanco', 0], ['Negro', 0], ['Rojo', 0], ['Verde', 0],
            ]],
            ['TÉ MATCHA', 'Té matcha tradicional', 3000, 3, []],
            ['TÉ CHAI', 'Té chai especiado', 2500, 4, []],
            ['TÉ DE CERRO', 'Té de cerro', 2500, 5, []],
            ['TÉ CHAI LATTE', 'Té chai con leche', 3500, 6, []],
            ['TÉ MATCHA LATTE', 'Matcha con leche', 3500, 7, []],
            ['TÉ CACAO', 'Té de cacao', 2000, 8, []],
            ['TÉ FRUTAL', 'Té con frutas', 3500, 9, []],
            ['MIX HERBAL', 'Mezcla de hierbas', 3000, 10, []],
            ['LIMONADA - MIEL', 'Limonada con miel', 3500, 11, []],
            ['CEREMONIA DEL TÉ', 'Ceremonia tradicional del té', 5000, 12, []],
            ['LECHE DORADA', 'Leche dorada con especias', 3000, 13, []],
            ['MAQUI', 'Té de maqui', 3000, 14, []],
        ];

        foreach ($teas as [$name, $desc, $price, $sort, $variantExtras]) {
            $extras = array_merge($variantExtras, self::TEA_EXTRAS);
            self::insertItem($db, 2, $name, $desc, $price, $sort, null, [], $extras);
        }
    }

    private static function seedSaladas(PDO $db): void
    {
        $items = [
            ['TOSTADAS', 'Elige dos ingredientes. En ciabatta o miga.', 5000, 1,
                [], [['Queso fresco', 0], ['Palta huevo', 0], ['Jamón', 0], ['Tomate', 0], ['Queso chanco', 0], ['Queso crema', 0]]],
            ['PLANCHADO', 'Jamón y queso en ciabatta o miga', 4000, 2, [], []],
            ['TOSCANO', 'Ciabatta, jamón, queso, tomate, orégano, rúcula y salsa', 5000, 3, [], []],
            ['MIGA POLLO', 'Pan de miga con pasta de pollo', 4000, 4, [], []],
            ['PESTO', 'Pan de miga o ciabatta, pesto, queso fresco, tomate y hojas verdes', 5000, 5, [], []],
            ['MADRILEÑO', 'Croissant con jamón serrano y queso mozzarella', 6000, 6, [], []],
            ['PAILA DE HUEVO', '4 huevos y pan tostado', 4500, 7, [], []],
            ['PAILA SABORES', 'Base de huevo más 1 ingrediente: queso, huancaína, tomate, jamón o ají', 5000, 8,
                [], [['Queso', 0], ['Huancaína', 0], ['Tomate', 0], ['Jamón', 0], ['Ají', 0]]],
            ['PIZZA A LA CREMA (UNTABLE)', 'Tomate, jamón, queso, orégano, salsa de tomate y crema con trozos de pan', 6000, 9, [], []],
        ];

        foreach ($items as [$name, $desc, $price, $sort, $ings, $extras]) {
            self::insertItem($db, 3, $name, $desc, $price, $sort, null, $ings, $extras);
        }
    }

    private static function seedPasteleria(PDO $db): void
    {
        $items = [
            ['WAFFLE CON FRUTA O HELADO', 'Con salsa y crema chantilly', 5500, 1],
            ['WAFFLE CON FRUTA Y HELADO', 'Con salsa y crema chantilly', 6500, 2],
            ['WAFFLE SOLO CON SALSA', 'Waffle con salsa', 3500, 3],
            ['CROISSANT DULCE', 'Croissant dulce', 3500, 4],
            ['CROISSANT + FRUTA, SALSA Y CREMA', 'Croissant con fruta, salsa y crema', 4500, 5],
            ['CHEESECAKE DEL DÍA', 'Cheesecake del día', 4000, 6],
            ['PIE DEL DÍA', 'Pie del día', 3500, 7],
            ['KUCHEN DEL DÍA', 'Kuchen del día', 4000, 8],
            ['TORTA DEL DÍA', 'Torta del día', 4000, 9],
            ['BROWNIE', 'Brownie', 3000, 10],
            ['BROWNIE CON HELADO', 'Brownie con helado', 4500, 11],
            ['DONUTS', 'Donuts', 2000, 12],
        ];

        foreach ($items as [$name, $desc, $price, $sort]) {
            self::insertItem($db, 4, $name, $desc, $price, $sort);
        }
    }

    private static function seedHeladas(PDO $db): void
    {
        $heladasExtras = array_merge(self::HELADAS_EXTRAS_500, [['Nutella', 1000]]);

        $items = [
            ['COPA HELADO SIMPLE', 'Copa de helado simple', 2500, 1],
            ['COPA HELADOS DOBLE', 'Copa de helado doble', 4000, 2],
            ['COPA HELADOS XL', 'Copa de helado XL', 6000, 3],
            ['CAFÉ HELADO', 'Café helado', 5000, 4],
            ['MILKSHAKE', 'Milkshake', 5000, 5],
            ['FRAPPUCCINO', 'Frappuccino', 5000, 6],
        ];

        foreach ($items as [$name, $desc, $price, $sort]) {
            self::insertItem($db, 5, $name, $desc, $price, $sort, null, [], $heladasExtras);
        }
    }

    private static function seedBebidasFrias(PDO $db): void
    {
        $items = [
            ['ICED COFFEE CREAM VARIEDADES', 'Iced coffee cream variedades', 4500, 1, [], []],
            ['ICED COFFEE VARIEDADES', 'Iced coffee variedades', 4000, 2, [], []],
            ['ICED MATCHA LATTE', 'Iced matcha latte', 5000, 3, [], []],
            ['ICED BUTTERFLY LEMON', 'Iced butterfly lemon', 3500, 4, [], []],
            ['ICED IRLANDÉS', 'Iced irlandés', 4000, 5, [], []],
            ['ICED ESPRESSO NARANJA', 'Iced espresso naranja', 5000, 6, [], []],
            ['TÉ FRUTAL', 'Té frutal frío', 3000, 7, [], []],
            ['VENECIANO', 'Veneciano', 3500, 8, [], []],
            ['ICED INFUSION', 'Iced infusion', 2500, 9, [], []],
            ['BARBIE', 'Bebida Barbie', 5000, 10, [], []],
            ['LIMONADA', 'Limonada', 3500, 11, [], []],
            ['NECTAR / BEBIDAS', 'Néctar o bebidas', 1800, 12, [], []],
            ['AGUA MINERAL', 'Agua mineral', 1800, 13, [], []],
            ['JUGO NATURAL', 'Jugo natural', 3500, 14, [], []],
            ['FRUTA CON LECHE', 'Frutilla, mango o plátano', 4000, 15,
                [], [['Frutilla', 0], ['Mango', 0], ['Plátano', 0]]],
        ];

        foreach ($items as [$name, $desc, $price, $sort, $ings, $extras]) {
            self::insertItem($db, 6, $name, $desc, $price, $sort, null, $ings, $extras);
        }
    }
}
