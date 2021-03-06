import React from 'react';
import { connect } from 'react-redux';
import { bindActionCreators } from 'redux';
import { withRouter } from 'react-router';
import SilverStripeComponent from 'lib/SilverStripeComponent';
import backend from 'lib/Backend';
import Config from 'lib/Config';
import i18n from 'i18n';
import * as galleryActions from 'state/gallery/GalleryActions';
import * as editorActions from 'state/editor/EditorActions';
import * as breadcrumbsActions from 'state/breadcrumbs/BreadcrumbsActions';
import Editor from 'containers/Editor/Editor';
import Gallery from 'containers/Gallery/Gallery';
import Breadcrumb from 'components/Breadcrumb/Breadcrumb';
import Toolbar from 'components/Toolbar/Toolbar';

class AssetAdmin extends SilverStripeComponent {

  constructor() {
    super();
    this.handleOpenFile = this.handleOpenFile.bind(this);
    this.handleCloseFile = this.handleCloseFile.bind(this);
    this.delete = this.delete.bind(this);
    this.handleSubmitEditor = this.handleSubmitEditor.bind(this);
    this.handleOpenFolder = this.handleOpenFolder.bind(this);
    this.createEndpoint = this.createEndpoint.bind(this);
    this.handleBackButtonClick = this.handleBackButtonClick.bind(this);
    this.handleFolderIcon = this.handleFolderIcon.bind(this);
    this.compare = this.compare.bind(this);

    // Build API callers from the URLs provided in configuration.
    // In time, something like a GraphQL endpoint might be a better way to run.
    const sectionConfig = Config.getSection('SilverStripe\\AssetAdmin\\Controller\\AssetAdmin');
    this.endpoints = {
      createFolderApi: this.createEndpoint(sectionConfig.createFolderEndpoint),
      readFolderApi: this.createEndpoint(sectionConfig.readFolderEndpoint, false),
      updateFolderApi: this.createEndpoint(sectionConfig.updateFolderEndpoint),
      deleteApi: this.createEndpoint(sectionConfig.deleteEndpoint),
    };
  }

  /**
   * Create a new endpoint
   *
   * @param {Object} endpointConfig
   * @param {Boolean} includeToken
   * @returns {Function}
     */
  createEndpoint(endpointConfig, includeToken = true) {
    return backend.createEndpointFetcher(Object.assign(
      {},
      endpointConfig,
      includeToken ? { defaultData: { SecurityID: Config.get('SecurityID') } } : {}
    ));
  }

  componentWillReceiveProps(props) {
    const viewChanged = this.compare(this.props.folder, props.folder);
    if (viewChanged) {
      this.setBreadcrumbs(props.folder);
    }
  }

  /**
   * Navigate to parent folder
   *
   * @param {Object} event
   */
  handleBackButtonClick(event) {
    event.preventDefault();
    if (this.props.folder) {
      this.handleOpenFolder(this.props.folder.parentID || 0);
    } else {
      this.handleOpenFolder(0);
    }
  }

  /**
   * Assign breadcrumbs from selected folder
   *
   * @param {Object} folder
     */
  setBreadcrumbs(folder) {
    const base = this.props.sectionConfig.url;

    // Set root breadcrumb
    const breadcrumbs = [{
      text: i18n._t('AssetAdmin.FILES', 'Files'),
      href: `/${base}/`,
    }];

    if (folder && folder.id) {
      // Add parent folders
      if (folder.parents) {
        folder.parents.forEach((parent) => {
          breadcrumbs.push({
            text: parent.title,
            href: `/${base}/show/${parent.id}`,
          });
        });
      }

      // Add current folder
      breadcrumbs.push({
        text: folder.title,
        href: `/${base}/show/${folder.id}`,
        icon: {
          className: 'icon font-icon-edit-list',
          action: this.handleFolderIcon,
        },
      });
    }

    this.props.actions.breadcrumbsActions.setBreadcrumbs(breadcrumbs);
  }

  /**
   * Check if either of the two objects differ
   *
   * @param {Object} left
   * @param {Object} right
     */
  compare(left, right) {
    // Check for falsiness
    if (left && !right || right && !left) {
      return true;
    }

    // Fall back to object comparison
    return left && right && (left.id !== right.id || left.name !== right.name);
  }

  handleFolderIcon(event) {
    this.handleOpenFile(this.props.folderId);

    event.preventDefault();
  }

  handleOpenFile(fileId) {
    const base = this.props.sectionConfig.url;
    this.props.router.push(`/${base}/show/${this.props.folderId}/edit/${fileId}`);
  }

  handleSubmitEditor(data, action, submitFn) {
    return submitFn()
      .then((response) => {
        this.props.actions.gallery.loadFile(this.props.fileId, response.record);
        return response;
      });
  }

  handleCloseFile() {
    this.handleOpenFolder(this.props.folderId);
  }

  handleOpenFolder(folderId) {
    const base = this.props.sectionConfig.url;
    this.props.router.push(`/${base}/show/${folderId}`);
  }

  /**
   * Delete a file or folder
   *
   * @param {number} fileId
   */
  delete(fileId) {
    let file = this.props.files.find((next) => next.id === fileId);
    if (!file && this.props.folder && this.props.folder.id === fileId) {
      file = this.props.folder;
    }
    if (!file) {
      throw new Error('File selected for deletion cannot be found');
    }
    const parentId = file.parent ? file.parent.id : 0;

    // eslint-disable-next-line no-alert
    if (confirm(i18n._t('AssetAdmin.CONFIRMDELETE'))) {
      this.props.actions.gallery.deleteItems(this.endpoints.deleteApi, [file.id])
        .then(() => {
          const base = this.props.sectionConfig.url;
          this.props.router.push(`/${base}/show/${parentId}`);
        });
    }
  }

  render() {
    const sectionConfig = this.props.sectionConfig;
    const createFileApiUrl = sectionConfig.createFileEndpoint.url;
    const createFileApiMethod = sectionConfig.createFileEndpoint.method;
    const file = this.props.files.find((next) => next.id === parseInt(this.props.fileId, 10));

    const editor = ((file || this.props.fileId === this.props.folderId) &&
      <Editor
        fileId={this.props.fileId}
        onClose={this.handleCloseFile}
        editFileSchemaUrl={sectionConfig.form.FileEditForm.schemaUrl}
        actions={this.props.actions.editor}
        onSubmit={this.handleSubmitEditor}
        onDelete={this.delete}
        addToCampaignSchemaUrl={sectionConfig.form.AddToCampaignForm.schemaUrl}
      />
    );

    const showBackButton = !!(this.props.folder && this.props.folder.id);

    return (
      <div className="fill-height">
        <Toolbar showBackButton={showBackButton} handleBackButtonClick={this.handleBackButtonClick}>
          <Breadcrumb multiline crumbs={this.props.breadcrumbs} />
        </Toolbar>
        <div className="flexbox-area-grow fill-width fill-height gallery">
          <Gallery
            files={this.props.files}
            fileId={this.props.fileId}
            folderId={this.props.folderId}
            folder={this.props.folder}
            name={this.props.name}
            limit={this.props.limit}
            page={this.props.page}
            bulkActions={this.props.bulkActions}
            createFileApiUrl={createFileApiUrl}
            createFileApiMethod={createFileApiMethod}
            createFolderApi={this.endpoints.createFolderApi}
            readFolderApi={this.endpoints.readFolderApi}
            updateFolderApi={this.endpoints.updateFolderApi}
            deleteApi={this.endpoints.deleteApi}
            onOpenFile={this.handleOpenFile}
            onOpenFolder={this.handleOpenFolder}
            sectionConfig={this.props.sectionConfig}
          />
          {editor}
        </div>
      </div>
    );
  }
}

AssetAdmin.propTypes = {
  config: React.PropTypes.shape({
    forms: React.PropTypes.shape({
      editForm: React.PropTypes.shape({
        schemaUrl: React.PropTypes.string,
      }),
    }),
  }),
  sectionConfig: React.PropTypes.shape({
    url: React.PropTypes.string,
  }),
  file: React.PropTypes.object,
  folder: React.PropTypes.shape({
    id: React.PropTypes.number,
    title: React.PropTypes.string,
    parents: React.PropTypes.array,
    parentID: React.PropTypes.number,
    canView: React.PropTypes.bool,
    canEdit: React.PropTypes.bool,
  }),
};

function mapStateToProps(state, ownProps) {
  const sectionConfigKey = 'SilverStripe\\AssetAdmin\\Controller\\AssetAdmin';
  const sectionConfig = state.config.sections[sectionConfigKey];
  const folder = state.assetAdmin.gallery.folder;
  const files = state.assetAdmin.gallery.files;

  return {
    breadcrumbs: state.breadcrumbs,
    sectionConfig,
    fileId: parseInt(ownProps.params.fileId, 10),
    folderId: parseInt(ownProps.params.folderId, 10),
    files,
    folder,
    limit: sectionConfig.limit,
  };
}

function mapDispatchToProps(dispatch) {
  return {
    actions: {
      gallery: bindActionCreators(galleryActions, dispatch),
      editor: bindActionCreators(editorActions, dispatch),
      breadcrumbsActions: bindActionCreators(breadcrumbsActions, dispatch),
    },
  };
}

export default withRouter(connect(mapStateToProps, mapDispatchToProps)(AssetAdmin));
