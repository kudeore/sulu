// @flow
import {observable} from 'mobx';
import log from 'loglevel';
import ResourceStore from '../../../stores/ResourceStore';
import FormInspector from '../FormInspector';
import ResourceFormStore from '../stores/ResourceFormStore';

jest.mock('loglevel', () => ({
    warn: jest.fn(),
}));

jest.mock('../../../stores/ResourceStore', () => jest.fn(function(resourceKey, id, options) {
    this.resourceKey = resourceKey;
    this.id = id;

    if (options) {
        this.locale = options.locale;
    }
}));

jest.mock('../stores/ResourceFormStore', () => jest.fn(function(resourceStore) {
    this.resourceKey = resourceStore.resourceKey;
    this.id = resourceStore.id;
    this.locale = resourceStore.locale;
    this.data = resourceStore.data;
    this.options = {};
    this.schema = {};
    this.getValueByPath = jest.fn();
    this.getValuesByTag = jest.fn();
    this.getSchemaEntryByPath = jest.fn();
    this.finishField = jest.fn();
    this.isFieldModified = jest.fn();
}));

test('Should return the resourceKey from the ResourceFormStore', () => {
    const formStore = new ResourceFormStore(new ResourceStore('test'), 'test');
    const formInspector = new FormInspector(formStore);

    expect(formInspector.resourceKey).toEqual('test');
});

test('Should return the locale from the ResourceFormStore', () => {
    const formStore = new ResourceFormStore(new ResourceStore('test', 1, {locale: observable.box('de')}), 'test');
    const formInspector = new FormInspector(formStore);

    if (!formInspector.locale) {
        throw new Error('Locale must have a value in formInspector');
    }

    expect(formInspector.locale.get()).toEqual('de');
});

test('Should return the id from the ResourceFormStore', () => {
    const formStore = new ResourceFormStore(new ResourceStore('test', 3), 'test');
    const formInspector = new FormInspector(formStore);

    expect(formInspector.id).toEqual(3);
});

test('Should return the errors from the ResourceFormStore', () => {
    const formStore = new ResourceFormStore(new ResourceStore('test', 3), 'test');
    formStore.errors = {};
    const formInspector = new FormInspector(formStore);

    expect(formInspector.errors).toBe(formStore.errors);
});

test('Should return the metadataOptions from the ResourceFormStore', () => {
    const formStore = new ResourceFormStore(new ResourceStore('test', 1), 'test');
    formStore.metadataOptions = {
        webspace: 'example',
    };
    const formInspector = new FormInspector(formStore);

    expect(formInspector.metadataOptions).toEqual({
        webspace: 'example',
    });
});

test('Should return the options from the ResourceFormStore', () => {
    const formStore = new ResourceFormStore(new ResourceStore('test', 1), 'test');
    formStore.options = {
        webspace: 'example',
    };
    const formInspector = new FormInspector(formStore);

    expect(formInspector.options).toEqual({
        webspace: 'example',
    });
});

test('Should return the value for a path by using the ResourceFormStore', () => {
    const data = [];
    const formStore = new ResourceFormStore(new ResourceStore('test', 3), 'test');
    // $FlowFixMe
    formStore.getValueByPath.mockReturnValue(data);
    const formInspector = new FormInspector(formStore);

    expect(formInspector.getValueByPath('/test')).toBe(data);
    expect(formStore.getValueByPath).toBeCalledWith('/test');
});

test('Should return the values for a given tag by using the ResourceFormStore', () => {
    const data = [];
    const formStore = new ResourceFormStore(new ResourceStore('test', 3), 'test');
    formStore.getValuesByTag.mockReturnValue(data);
    const formInspector = new FormInspector(formStore);

    expect(formInspector.getValuesByTag('/test')).toBe(data);
    expect(formStore.getValuesByTag).toBeCalledWith('/test');
});

test('Should call finishField method from formStore', () => {
    const formStore = new ResourceFormStore(new ResourceStore('test', 3), 'test');
    const formInspector = new FormInspector(formStore);

    formInspector.finishField('/block/0/test', '/test');

    expect(formStore.finishField).toBeCalledWith('/block/0/test');
});

test('Should call registered onFinishField handlers', () => {
    const formInspector = new FormInspector(new ResourceFormStore(new ResourceStore('test', 3), 'test'));
    const finishFieldHandler1 = jest.fn();
    const finishFieldHandler2 = jest.fn();
    formInspector.addFinishFieldHandler(finishFieldHandler1);
    formInspector.addFinishFieldHandler(finishFieldHandler2);

    formInspector.finishField('/block/0/test', '/test');
    expect(finishFieldHandler1).toBeCalledWith('/block/0/test', '/test');
    expect(finishFieldHandler2).toBeCalledWith('/block/0/test', '/test');
});

test.each([
    ['draft'],
    ['action'],
])('Should call registered save handlers with action parameter "%s"', (action) => {
    const formInspector = new FormInspector(new ResourceFormStore(new ResourceStore('test', 3), 'test'));
    const saveHandler1 = jest.fn();
    const saveHandler2 = jest.fn();
    formInspector.addSaveHandler(saveHandler1);
    formInspector.addSaveHandler(saveHandler2);

    formInspector.triggerSaveHandler(action);
    expect(saveHandler1).toBeCalledWith(action);
    expect(saveHandler2).toBeCalledWith(action);
    expect(log.warn).toBeCalled();
});

test.each([
    [undefined],
    [{action: 'value'}],
    [{inherit: true}],
])('Should call regierested save handlers with options parameter "%s"', (options) => {
    const formInspector = new FormInspector(new ResourceFormStore(new ResourceStore('test', 3), 'test'));
    const saveHandler1 = jest.fn();
    const saveHandler2 = jest.fn();
    formInspector.addSaveHandler(saveHandler1);
    formInspector.addSaveHandler(saveHandler2);

    formInspector.triggerSaveHandler(options);
    expect(saveHandler1).toBeCalledWith(options);
    expect(saveHandler2).toBeCalledWith(options);
    expect(log.warn).not.toBeCalled();
});

test('Should return the SchemaEntry for a given path by using the ResourceFormStore', () => {
    const schemaEntry = {
        type: 'text_line',
    };
    const formStore = new ResourceFormStore(new ResourceStore('test', 3), 'test');
    formStore.getSchemaEntryByPath.mockReturnValue(schemaEntry);
    const formInspector = new FormInspector(formStore);

    expect(formInspector.getSchemaEntryByPath('/test')).toBe(schemaEntry);
    expect(formStore.getSchemaEntryByPath).toBeCalledWith('/test');
});

test('Should return if a field is modified by using the ResourceFormStore', () => {
    const formStore = new ResourceFormStore(new ResourceStore('test', 3), 'test');
    formStore.isFieldModified.mockReturnValue(true);
    const formInspector = new FormInspector(formStore);

    expect(formInspector.isFieldModified('/test')).toBe(true);
    expect(formStore.isFieldModified).toBeCalledWith('/test');
});
