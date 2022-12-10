<?php

    namespace stankata90\yii2GridView;

    use yii\db\Query;
    use yii\helpers\ArrayHelper;

    class GridView extends \yii\grid\GridView
    {
        public $uniqieId;
        public static $uniqieIdCounter = 0;

        public function init()
        {
            $this->initGridView();

            parent::init();
        }

        protected function initGridView()
        {
            if ( !$this->uniqieId ) {
                $this->uniqueId();
            }
            $this->options['uniqieId'] = $this->uniqieId;
        }


        /**
         * Генерира уникално ид за уиджета на база ендпойта на изпълнение модул/модул.../контроллер/екшън/ид
         *
         * @return void
         */
        protected function uniqueId()
        {
            $endpoint = [
                \yii::$app->controller->id,
                \yii::$app->controller->action->id,
                'grid-view-' . static::$uniqieIdCounter++,
            ];

            $module = \yii::$app->controller->module;
            while ( $module ) {
                array_unshift( $endpoint, $module->id );
                $module = $module->module;
            }

            $this->uniqieId = implode( '/', $endpoint );
        }

        protected function initColumns()
        {
            $tableName = 'grid_view_column';

            /**
             * Правим колекция с ключова за колконите от уиджета.
             */
            $colFromWidget = [];
            foreach ( $this->columns as $i => $column ) {
                if ( is_string( $column ) ) {
                    $colFromWidget[] = $column;
                } else {
                    if ( isset( $column['attribute'] ) && is_string( $column['attribute'] ) ) {
                        $colFromWidget[] = $column['attribute'];
                    } else if ( isset( $column['class'] ) && is_string( $column['class'] ) ) {
                        $colFromWidget[] = $column['class'];
                    }
                }
            }
            /**
             * Правим колекция с ключова за колконите от уиджета.
             */

            /**
             * взимаме всички налични колони за това уникално ид и създаваме тези които не съществуват
             */
            $dbColumn = ( new Query() )
                ->from( $tableName )
                ->where( [
                    'id'  => $this->uniqieId,
                    'col' => $colFromWidget,
                ] )
                ->orderBy( 'sort' )
                ->all();

            $bulk     = [];
            $initSort = count( $dbColumn ) ? $dbColumn[ count( $dbColumn ) - 1 ]['sort'] + 1 : 0;
            foreach ( array_diff( $colFromWidget, array_column( $dbColumn, 'col' ) ) as $col ) {
                $bulk[] = [
                    'id'     => $this->uniqieId,
                    'col'    => $col,
                    'sort'   => $initSort++,
                    'hidden' => 0,
                    'active' => 1,
                ];
            }

            if ( count( $bulk ) ) {
                \Yii::$app->db->createCommand()->batchInsert( $tableName, [ 'id', 'col', 'sort', 'hidden', 'active', ], $bulk )->execute();
            }
            /**
             * взимаме всички налични колони за това уникално ид и създаваме тези които не съществуват
             */

            /**
             * взимаме всички налични колони за това уникално ид с новосъздадените колони
             */
            $dbColumn = ArrayHelper::map( ( new Query() )
                ->from( $tableName )
                ->where( [
                    'id'  => $this->uniqieId,
                    'col' => $colFromWidget,
                ] )
                ->orderBy( 'sort' )
                ->all(), 'col', function ( $arr ) {
                return $arr;
            } );
            /**
             * взимаме всички налични колони за това уникално ид с новосъздадените колони
             */

            /**
             * Филтрираме кои колони трябва да се показват
             */
            $dbColumn      = array_filter( $dbColumn, function ( $col ) {
                return $col['hidden'] == 0 && $col['active'] == 1;
            } );
            $colFromWidget = array_filter( $colFromWidget, function ( $col ) use ( $dbColumn ) {
                return isset( $dbColumn[ $col ] );
            } );
            /**
             * Филтрираме кои колони трябва да се показват
             */

            /**
             * Сортираме и презаписваме колоните преди обработката на уиджета
             */
            uasort( $colFromWidget, function ( $a, $b ) use ( $dbColumn ) {
                return $dbColumn[ $a ]['sort'] <=> $dbColumn[ $b ]['sort'];
            } );

            $keyMap  = array_keys( $this->columns );
            $columns = [];
            foreach ( $colFromWidget as $k => $col ) {
                $columns[ $keyMap[ $k ] ] = $this->columns[ $keyMap[ $k ] ];
            }

            unset( $this->columns['name'] );
            $this->columns = $columns;
            /**
             * Сортираме и презаписваме колоните преди обработката на уиджета
             */

            parent::initColumns();
        }
    }