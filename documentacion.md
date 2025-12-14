🆕 NUEVAS TABLAS CREADAS (5)
1. product_materials
Propósito: Almacenar materiales disponibles por categoría
Evidencia: Excel muestra 15 materiales diferentes
Campos clave:

name: B. Delgado, Vinil Econ 120gr, Foam, etc.
unit_price: Precio por m²
max_width, max_height: Restricciones (ej: Foam máx. 1.20mt)
c

2. product_finishes
Propósito: Almacenar acabados disponibles
Evidencia: Doc 1 y Excel
Campos clave:

name: impreso, c/ojales, c/tubos, termosellado
additional_cost: Costo adicional por acabado
requires_quantity: Si requiere cantidad (ej: ojales)


3. customer_price_tiers
Propósito: Niveles de precio por tipo de cliente
Evidencia: Excel muestra 3 niveles
Registros:

Estándar
Por Mayor
Campaña Política


4. product_configurations
Propósito: Configuración específica por producto en pedidos/cotizaciones
Evidencia: Productos como "B. Grueso 600 x 300 + termosellado + ojales(20)"
Campos clave:

width, height: Dimensiones en metros
material_id: Material seleccionado
finish_1_id, finish_2_id, finish_3_id: Hasta 3 acabados
finish_1_quantity, etc.: Cantidades (ej: 20 ojales)


5. production_tracking
Propósito: Seguimiento de producción
Evidencia: Doc 2 - Hoja de Metraje completa
Campos clave:

production_number: Número de producción (00073)
responsible_employee_id, supervisor_employee_id: Responsables
material_used, material_waste, material_missing: Control de material (Mt, M, F)
started_at, completed_at: Fechas ingreso/término


⚠️ TABLAS MODIFICADAS (5)
1. customers - +3 campos
sql+ sales_channel VARCHAR(50)       -- Redes, Ferias, Tiktok, etc.
+ customer_type VARCHAR(50)       -- Negocio, Empresa, Diseño, etc.
+ price_tier_id BIGINT UNSIGNED   -- FK a customer_price_tiers
2. orders - +12 campos
sql+ designer_id BIGINT UNSIGNED     -- Diseñador asignado (Hellen, Peter)
+ production_number VARCHAR(20)   -- Número de producción (00073)
+ delivery_date DATE              -- Fecha de entrega
+ delivery_time TIME              -- Hora de entrega
+ delivery_type ENUM              -- local, tercero, instalacion
+ production_start_time TIME      -- H. DE INICIO
+ production_end_time TIME        -- PROD. FINAL
+ delivery_province VARCHAR(100)
+ delivery_recipient_name VARCHAR(255)
+ delivery_recipient_phone VARCHAR(20)
+ delivery_recipient_dni VARCHAR(15)
+ delivery_destination VARCHAR(255)
3. payments - +1 campo
sql+ installment_number INT          -- Número de adelanto (1-5)
4. employees - +1 campo
sql+ employee_role VARCHAR(50)       -- diseñador, comercial, responsable_produccion, supervisor
5. quotations - +1 campo
sql+ commercial_employee_id BIGINT UNSIGNED  -- Comercial asignado (Mayra Jara)



📦 DATOS INICIALES INSERTADOS (18 registros)
Niveles de Precio (3):

Estándar
Por Mayor
Campaña Política

Materiales BANER (4):

B. Delgado
B. Grueso
B. Blackout
Lona Trasluc. (restricción: alto máx. 1.2 mt)

Materiales VINIL (11):

Econ 120 gr (S/. 18.00)
Chino 140 gr (S/. 22.50)
Intertak 120 gr (S/. 25.20)
Intertak Premium (S/. 27.00)
Arclad (S/. 37.80)
Pavonado (S/. 40.50)
Pavonado c/color (S/. 45.00)
Microperforado (S/. 31.50)
Vinil Traslucido (S/. 36.00)
Foam (S/. 38.25, restricción: ancho máx. 1.20 mt)
Celtex (S/. 56.25, restricción: ancho máx. 1.20 mt)

Acabados (4):

Impreso (S/. 0.00)
Con ojales (S/. 3.00, requiere cantidad)
Con tubos (S/. 12.00)
Termosellado (S/. 0.00)


🔗 NUEVAS RELACIONES CREADAS
customers → customer_price_tiers (price_tier_id)
orders → employees (designer_id)
quotations → employees (commercial_employee_id)
product_configurations → product_materials (material_id)
product_configurations → product_finishes (finish_1_id, finish_2_id, finish_3_id)
production_tracking → orders (order_id)
production_tracking → employees (responsible_employee_id, supervisor_employee_id)
product_materials → product_categories (category_id)