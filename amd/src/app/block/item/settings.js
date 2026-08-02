export const NAME_CONFLICT = {
    SKIP: 'skip',
    RESOLVE: 'resolve',
};

export const SECTION_MATCH = {
    NAME: 'name',
    POSITION: 'position',
};

export const SECTION_MISSING = {
    SKIP: 'skip',
    CREATE: 'create',
};

export const VISIBILITY = {
    SOURCE: 'source',
    SHOW: 'show',
    HIDE: 'hide',
};

// The id prefixes for the two fields that are looked up by id rather than by
// name (see SETTINGS_FIELDS below)
const SECTION_SELECT_ID_PREFIX = 'section-select-';
const VISIBILITY_SELECT_ID_PREFIX = 'visibility-select-';

export const SETTINGS_FIELDS = [
    {
        key: 'nameconflict',
        defaultValue: NAME_CONFLICT.RESOLVE,
        read: (root, cmid) =>
            root.querySelector(`input[name="nameconflict-${cmid}"]:checked`)?.value,
    },
    {
        key: 'section',
        defaultValue: '',
        idPrefix: SECTION_SELECT_ID_PREFIX,
        read: (root, cmid) =>
            root.querySelector(`#${SECTION_SELECT_ID_PREFIX}${cmid}`)?.value,
    },
    {
        key: 'sectionname',
        defaultValue: '',
        read: (root, cmid) =>
            root.querySelector(`#${SECTION_SELECT_ID_PREFIX}${cmid}`)?.selectedOptions[0]?.text,
    },
    {
        key: 'sectionmatch',
        defaultValue: SECTION_MATCH.NAME,
        read: (root, cmid) =>
            root.querySelector(`input[name="sectionmatch-${cmid}"]:checked`)?.value,
    },
    {
        key: 'sectionmissing',
        defaultValue: SECTION_MISSING.CREATE,
        read: (root, cmid) =>
            root.querySelector(`input[name="sectionmissing-${cmid}"]:checked`)?.value,
    },
    {
        key: 'visibility',
        defaultValue: VISIBILITY.SOURCE,
        idPrefix: VISIBILITY_SELECT_ID_PREFIX,
        read: (root, cmid) =>
            root.querySelector(`#${VISIBILITY_SELECT_ID_PREFIX}${cmid}`)?.value,
    },
    {
        key: 'userdata',
        defaultValue: '0',
        read: (root, cmid) =>
            root.querySelector(`#keep-userdata-${cmid}`)?.checked ? '1' : '0',
    },
    {
        key: 'restrictions',
        defaultValue: '1',
        read: (root, cmid) =>
            root.querySelector(`#keep-restrictions-${cmid}`)?.checked ? '1' : '0',
    },
];

export default {
    NAME_CONFLICT,
    SECTION_MATCH,
    SECTION_MISSING,
    VISIBILITY,
    SETTINGS_FIELDS,
};
