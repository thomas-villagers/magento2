// jscs:disable requireDotNotation
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
// jscs:disable jsDoc
define([
    'uiComponent',
    'jquery',
    'ko',
    'underscore',
    'Magento_Ui/js/modal/alert',
    'uiRegistry'
], function (Component, $, ko, _, alert, registry) {
    'use strict';

    function UserException(message) {
        this.message = message;
        this.name = 'UserException';
    }
    UserException.prototype = Object.create(Error.prototype);

    return Component.extend({
        defaults: {
            opened: false,
            attributes: [],
            usedAttributes: [],
            attributeCodes: [],
            attributesData: {},
            productMatrix: [],
            variations: [],
            productAttributes: [],
            disabledAttributes: [],
            fullAttributes: [],
            rowIndexToEdit: false,
            productAttributesMap: null,
            value: [],
            modules: {
                associatedProductGrid: '${ $.configurableProductGrid }',
                wizardButtonElement: '${ $.wizardModalButtonName }',
                formElement: '${ $.formName }',
                attributeSetHandlerModal: '${ $.attributeSetHandler }'

            },
            links: {
                value: '${ $.provider }:${ $.dataScopeVariations }',
                usedAttributes: '${ $.provider }:${ $.dataScopeAttributes }',
                attributesData: '${ $.provider }:${ $.dataScopeAttributesData }',
                attributeCodes: '${ $.provider }:${ $.dataScopeAttributeCodes }'
            }
        },
        initialize: function () {
            this._super();

            this.changeButtonWizard();
            this.initProductAttributesMap();
            this.disableConfigurableAttributes(this.productAttributes);
        },
        initObservable: function () {
            this._super().observe(
                'actions opened attributes productMatrix value usedAttributes attributesData attributeCodes'
            );

            return this;
        },
        _makeProduct: function (product) {
            var productId = product['entity_id'] || product.productId || null,
                attributes = _.pick(product, this.attributes.pluck('code')),
                options = _.map(attributes, function (option, attribute) {
                    var oldOptions = _.findWhere(this.attributes(), {
                            code: attribute
                        }).options,
                        result;

                    if (_.isFunction(oldOptions)) {
                        result = oldOptions.findWhere({
                            value: option
                        });
                    } else {
                        result = _.findWhere(oldOptions, {
                            value: option
                        });
                    }

                    return result;
                }.bind(this));

            return {
                attribute: JSON.stringify(attributes),
                editable: false,
                images: {
                    preview: product['thumbnail_src']
                },
                name: product.name || product.sku,
                options: options,
                price: parseFloat(product.price.replace(/[^\d.]+/g, '')).toFixed(4),
                productId: productId,
                productUrl: this.buildProductUrl(productId),
                quantity: product.quantity || null,
                sku: product.sku,
                status: product.status === undefined ? 1 : parseInt(product.status, 10),
                variationKey: this.getVariationKey(options),
                weight: product.weight || null
            };
        },
        getProductValue: function (name) {
            name = name.split('/').join('][');

            return $('[name="product[' + name + ']"]:enabled:not(.ignore-validate)', this.productForm).val();
        },
        getRowId: function (data, field) {
            var key = data.variationKey;

            return 'variations-matrix-' + key + '-' + field;
        },
        getVariationRowName: function (variation, field) {
            var result;

            if (variation.productId) {
                result = 'configurations[' + variation.productId + '][' + field.split('/').join('][') + ']';
            } else {
                result = 'variations-matrix[' + variation.variationKey + '][' + field.split('/').join('][') + ']';
            }

            return result;
        },
        render: function (variations, attributes) {
            this.changeButtonWizard();
            this.populateVariationMatrix(variations);
            this.attributes(attributes);
            this.disableConfigurableAttributes(attributes);
            this.handleValue(variations);
            this.handleAttributes();
        },
        changeButtonWizard: function () {
            if (this.variations.length) {
                this.wizardButtonElement().title(this.wizardModalButtonTitle);
            }
        },
        handleValue: function (variations) {
            var tmpArray = [];


            _.each(variations, function (variation) {
                var attributes = _.reduce(variation.options, function (memo, option) {
                    var attribute = {};
                    attribute[option['attribute_code']] = option.value;

                    return _.extend(memo, attribute);
                }, {});
                var gallery = {images: {}};
                var defaultImage = null;

                _.each(variation.images.images, function (image) {
                    gallery.images[image.file_id] = {
                        position: image.position,
                        file: image.file,
                        disabled: image.disabled,
                        label: ''
                    };
                    if (image.position == 1) {
                        defaultImage = image.file;
                    }
                }, this);

                tmpArray.push(_.extend(variation, {
                    productId: variation.productId || null,
                    name: variation.name || variation.sku,
                    priceCurrency: this.currencySymbol,
                    weight: variation.weight,
                    attribute: JSON.stringify(attributes),
                    variationKey: this.getVariationKey(variation.options),
                    editable: variation.editable === undefined ? 0 : 1,
                    productUrl: this.buildProductUrl(variation.productId),
                    status: variation.status === undefined ? 1 : parseInt(variation.status, 10),
                    newProduct: variation.productId ? 0 : 1,
                    media_gallery: gallery,
                    swatch_image: defaultImage,
                    small_image: defaultImage,
                    thumbnail: defaultImage,
                    image: defaultImage
                }));
            }, this);

            this.value(tmpArray);
        },
        handleAttributes: function () {
            var tmpArray = [];
            var codesArray = [];
            var tmpOptions = {};
            var option = {};
            var position = 0;
            var values = {};

            _.each(this.attributes(), function (attribute) {
                tmpArray.push(attribute.id);
                codesArray.push(attribute.code);
                values = {};
                _.each(attribute.chosen, function (row) {
                    values[row.value] = {
                        "include": "1",
                        "value_index": row.value
                    };
                }, this);
                option = {
                    "attribute_id": attribute.id,
                    "code": attribute.code,
                    "label": attribute.label,
                    "position": position,
                    "values": values
                };
                tmpOptions[attribute.id] = option;
                position++;
            }, this);

            this.attributesData(tmpOptions);
            this.usedAttributes(tmpArray);
            this.attributeCodes(codesArray);
        },


        /**
         * Get attributes options
         * @see use in matrix.phtml
         * @function
         * @event
         * @returns {array}
         */
        getAttributesOptions: function () {
            return this.showVariations() ? this.productMatrix()[0].options : [];
        },
        showVariations: function () {
            return this.productMatrix().length > 0;
        },
        populateVariationMatrix: function (variations) {
            this.productMatrix([]);
            _.each(variations, function (variation) {
                var attributes = _.reduce(variation.options, function (memo, option) {
                    var attribute = {};
                    attribute[option['attribute_code']] = option.value;

                    return _.extend(memo, attribute);
                }, {});
                this.productMatrix.push(_.extend(variation, {
                    productId: variation.productId || null,
                    name: variation.name || variation.sku,
                    weight: variation.weight,
                    attribute: JSON.stringify(attributes),
                    variationKey: this.getVariationKey(variation.options),
                    editable: variation.editable === undefined ? !variation.productId : variation.editable,
                    productUrl: this.buildProductUrl(variation.productId),
                    status: variation.status === undefined ? 1 : parseInt(variation.status, 10)
                }));
            }, this);
        },
        buildProductUrl: function (productId) {
            return this.productUrl.replace('%id%', productId);
        },
        getVariationKey: function (options) {
            return _.pluck(options, 'value').sort().join('-');
        },
        getProductIdByOptions: function (options) {
            return this.productAttributesMap[this.getVariationKey(options)] || null;
        },
        initProductAttributesMap: function () {
            if (this.productAttributesMap === null) {
                this.productAttributesMap = {};
                _.each(this.variations, function (product) {
                    this.productAttributesMap[this.getVariationKey(product.options)] = product.productId;
                }.bind(this));
            }
        },
        disableConfigurableAttributes: function (attributes) {
            var element;
            _.each(this.disabledAttributes, function (attribute) {
                registry.get('index = ' + attribute).disabled(false);
            });
            this.disabledAttributes = [];

            _.each(attributes, function (attribute) {
                element = registry.get('index = ' + attribute.code);
                if (!_.isUndefined(element)) {
                    element.disabled(true);
                    this.disabledAttributes.push(attribute.code);
                }
            }, this);
        },

        /**
         * Get currency symbol
         * @returns {*}
         */
        getCurrencySymbol: function () {
            return this.currencySymbol;
        },
        saveFormHandler: function(params) {
            if (this.checkForNewAttributes()) {
                this.attributeSetHandlerModal().openModal();
            } else {
                this.formElement().save(params);
            }
        },
        checkForNewAttributes: function () {
            var newAttributes = false;
            var element;
            _.each(this.source.get('data.attribute_codes'), function (attribute) {
                element = registry.get('index = ' + attribute);
                if (_.isUndefined(element)) {
                    newAttributes = true;
                }
            }, this);
            return newAttributes;
        }
    });
});
