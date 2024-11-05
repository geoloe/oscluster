<?php

function console_log($data) {
    $json_data = json_encode($data);
    echo "<script>console.log('PHP Debug: ' + $json_data);</script>";
}
// Function to get all files recursively, excluding certain directories
function getAllFiles($dir, $excludedDirs = ['styles', 'scripts']) {
    $files = [];
    $dirContents = scandir($dir);
    
    foreach ($dirContents as $item) {
        if ($item === '.' || $item === '..') {
            continue; // Skip current and parent directory references
        }
        
        $path = $dir . '/' . $item;
        
        if (is_dir($path)) {
            // Exclude specified directories
            if (!in_array($item, $excludedDirs)) {
                $files = array_merge($files, getAllFiles($path, $excludedDirs)); // Recursive call for directories
            }
        } else {
            $files[] = $path; // Add the file to the list
        }
    }
    
    return $files; // Return all found files
}
// Function to get the filtered list of files/directories for pagination and search
function getFilteredFileList($dir, $fileType = null, $search = null) {
    $allFiles = getAllFiles($dir); // Get all files recursively
    $filteredFiles = [];

    foreach ($allFiles as $file) {
        // Exclude PHP files
        if (pathinfo($file, PATHINFO_EXTENSION) === 'php') {
            continue; // Skip PHP files
        }

        // Check if fileType is specified and matches the file type
        if ($fileType && pathinfo($file, PATHINFO_EXTENSION) !== $fileType) {
            continue; // Skip files that do not match the filter
        }

        // Check if search query is present and matches the file name
        if ($search && stripos(basename($file), $search) === false) {
            continue; // Skip files that do not match the search query
        }

        $filteredFiles[] = $file; // Add to the list of filtered files
    }

    console_log($filteredFiles);

    return $filteredFiles;
}

// Fetch parameters from AJAX request
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10; // Number of items per page
$offset = ($currentPage - 1) * $limit;
$path = '.'; // Define the base directory

// Fetch fileType and search from the request, default to null
$fileType = isset($_GET['fileType']) ? $_GET['fileType'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;

// Get the filtered list of files for pagination and search
$filteredFiles = getFilteredFileList($path, $fileType, $search);
$totalFiles = count($filteredFiles);

// Sort the files based on user selection
if (isset($_GET['sortByDate']) && $_GET['sortByDate'] === 'asc') {
    usort($filteredFiles, fn($a, $b) => filemtime($a) <=> filemtime($b)); // Sort by date ascending
} elseif (isset($_GET['sortByDate']) && $_GET['sortByDate'] === 'desc') {
    usort($filteredFiles, fn($a, $b) => filemtime($b) <=> filemtime($a)); // Sort by date descending
} elseif (isset($_GET['sortBySize']) && $_GET['sortBySize'] === 'asc') {
    usort($filteredFiles, fn($a, $b) => filesize($a) <=> filesize($b)); // Sort by size ascending
} elseif (isset($_GET['sortBySize']) && $_GET['sortBySize'] === 'desc') {
    usort($filteredFiles, fn($a, $b) => filesize($b) <=> filesize($a)); // Sort by size descending
}

$totalPages = ceil($totalFiles / $limit);
$filesForPage = array_slice($filteredFiles, $offset, $limit);
$fileType = isset($_GET['fileType']) ? $_GET['fileType'] : ''; // Retrieve the file type
// Function to render file list
function renderFileList($filesForPage, $path) {
    foreach ($filesForPage as $file) {
        // Get file extension to determine file type for syntax highlighting
        $fileType = pathinfo($file, PATHINFO_EXTENSION);
        //$fileName = basename($file); // Get the file name only

        // Get file size and creation date
        $fileSize = filesize($file); // Get file size in bytes
        $fileCreationDate = date("F d, Y H:i:s", filemtime($file)); // Get creation date

        // Get the relative path for the breadcrumb
        $relativePath = str_replace($path . '/', '', $file); // Remove the base path
        $pathParts = explode('/', $relativePath); // Split the path into parts

        // Create breadcrumb string with links
        $breadcrumbItems = [];
        $currentPath = ''; // To build the current path for each part

        foreach ($pathParts as $index => $part) {
            $currentPath .= $part . '/'; // Build the current path
        
            // Check if it's the last part (the file)
            if ($index === count($pathParts) - 1) {
                // Truncate long file names
                $shortName = (strlen($part) > 20) ? substr($part, 0, 25) . '...' : $part;
        
                // If it's the file, create a clickable link with the relative path
                $breadcrumbItems[] = "<a href='{$relativePath}' onclick=\"showContent('{$file}', '{$fileType}')\">{$shortName}</a>";
            } else {
                // If it's a directory, just display the name without a link
                $breadcrumbItems[] = "<span>{$part}</span>";
            }
        }        

        // Join breadcrumb items with ' > '
        $breadcrumb = implode(' &gt; ', $breadcrumbItems);

        // Format file size to KB/MB for better readability
        $formattedSize = $fileSize < 1024 ? "{$fileSize} bytes" : ($fileSize < 1048576 ? round($fileSize / 1024, 2) . " KB" : round($fileSize / 1048576, 2) . " MB");

        // Create a wrapper for breadcrumb and file info
        echo "<div class='list-group-item d-flex flex-column flex-md-row justify-content-between align-items-start'>"; // Overall container
        echo "    <div class='d-flex flex-grow-1 align-items-end justify-content-between'>"; // Flexible div to allow space for text
        echo "        <span class='me-3'>{$breadcrumb}</span>"; // Show breadcrumb

        // Create a div for size and creation date, align text to center
        echo "        <div class='text-muted text-center flex-grow-1'>"; // Center-aligned container
        echo "            <span class='me-3'>Size: {$formattedSize}</span>"; // Show file size
        echo "            <span>Created: {$fileCreationDate}</span>"; // Show creation date
        echo "        </div>";
        echo "    </div>";

        // Button to show content, wrapped in a div for proper alignment
        echo "    <div class='mt-2 mt-md-0'>"; // Margin top for spacing on small screens
        echo "        <button class='btn btn-info btn-sm' onclick='showContent(\"{$file}\", \"{$fileType}\")'>Show Content</button>"; // Button to show content
        echo "    </div>";
        echo "</div>";
    }
}

// Render the file list
renderFileList($filesForPage, $path);

// Output pagination controls for AJAX
if ($totalPages > 1) {
    echo "<nav aria-label='File pagination'>";
    echo "    <ul class='pagination justify-content-center'>";
    
    if ($currentPage > 1) {
        $prevPage = $currentPage - 1;
        echo "<li class='page-item'><a class='page-link' href='#' onclick='loadPage($prevPage)'>Previous</a></li>";
    }
    
    for ($i = 1; $i <= $totalPages; $i++) {
        $activeClass = ($i == $currentPage) ? 'active' : '';
        echo "<li class='page-item $activeClass'><a class='page-link' href='#' onclick='loadPage($i)'>$i</a></li>";
    }
    
    if ($currentPage < $totalPages) {
        $nextPage = $currentPage + 1;
        echo "<li class='page-item'><a class='page-link' href='#' onclick='loadPage($nextPage)'>Next</a></li>";
    }
    
    echo "</ul>";
    echo "</nav>";
}
?>