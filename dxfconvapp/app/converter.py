import ezdxf
import matplotlib.pyplot as plt
from ezdxf.addons.drawing import RenderContext, Frontend
from ezdxf.addons.drawing.matplotlib import MatplotlibBackend
from ezdxf.math import Matrix44
from typing import List
from pathlib import Path

def convert_dxf_to_jpg(dxf_path: str, jpg_path: str, dpi: int = 600, color_map=None):
    """
    Convert DXF file to JPG image with optional color mapping based on source file.
    
    Args:
        dxf_path: Path to DXF file
        jpg_path: Path to output JPG file
        dpi: Resolution for JPG
        color_map: Optional dictionary mapping entity IDs to colors
    """
    doc = ezdxf.readfile(dxf_path)
    msp = doc.modelspace()

    # Get extents of the document to set appropriate figure size
    try:
        extents = msp.extents()
        if extents:
            width = extents.extmax.x - extents.extmin.x
            height = extents.extmax.y - extents.extmin.y
            # Use aspect ratio for figure, with minimum size
            ratio = max(width / height, height / width)
            figsize = (12 * ratio, 12)  # Larger figure size for better quality
        else:
            figsize = (12, 12)  # Default if no extents
    except:
        figsize = (12, 12)  # Default if extents fail
    
    # Create figure with calculated size - use larger figure for better resolution
    fig = plt.figure(figsize=figsize, facecolor='black', dpi=100)
    ax = fig.add_axes([0, 0, 1, 1])
    ax.set_facecolor('black')
    ax.set_axis_off()
    
    # Add debug info
    print(f"Creating figure with size: {figsize}, dpi: {dpi}")
    if 'extents' in locals():
        print(f"Drawing extents: min({extents.extmin.x}, {extents.extmin.y}), max({extents.extmax.x}, {extents.extmax.y})")

    # Create rendering context
    ctx = RenderContext(doc)
    backend = MatplotlibBackend(ax)
    
    # Apply custom colors if a color map is provided
    if color_map:
        # For colored output, we need to modify the entity colors in the DXF document
        # before rendering, since we can't easily access matplotlib artists after rendering
        
        # First, set custom colors for entities in the document
        # Set all entities to a bright color for better visibility
        for entity in msp:
            if hasattr(entity, 'dxf'):
                try:
                    # Default to white (7) for better visibility
                    entity.dxf.color = 7
                    
                    # Then apply specific colors from color map if available
                    if hasattr(entity.dxf, 'handle') and entity.dxf.handle in color_map:
                        handle = entity.dxf.handle
                        # Map common HTML colors to AutoCAD color indices
                        color = color_map[handle].upper()
                        if color == '#FF0000': entity.dxf.color = 1    # Red
                        elif color == '#FFFF00': entity.dxf.color = 2  # Yellow
                        elif color == '#00FF00': entity.dxf.color = 3  # Green
                        elif color == '#00FFFF': entity.dxf.color = 4  # Cyan
                        elif color == '#0000FF': entity.dxf.color = 5  # Blue
                        elif color == '#FF00FF': entity.dxf.color = 6  # Magenta
                        elif color == '#FFFFFF': entity.dxf.color = 7  # White
                        elif color == '#FFA500': entity.dxf.color = 30 # Orange
                        elif color == '#800080': entity.dxf.color = 200 # Purple
                        elif color == '#FFC0CB': entity.dxf.color = 10 # Pink
                except Exception as e:
                    print(f"Error setting color: {str(e)}")
                    # Some entities might not support color attribute
                    pass
        
        # Now render with the modified colors
        frontend = Frontend(ctx, backend)
        frontend.draw_layout(msp, finalize=True)
    else:
        # Standard drawing with default white color
        frontend = Frontend(ctx, backend)
        frontend.draw_layout(msp, finalize=True)
    
    # Save with tight bbox but add small padding to avoid clipping
    # Use higher quality settings for better output
    fig.savefig(jpg_path, dpi=dpi, bbox_inches='tight', pad_inches=0.05, 
                facecolor=fig.get_facecolor(), transparent=False, 
                edgecolor='none', orientation='landscape')
    plt.close(fig)
    return jpg_path

def detect_designs(msp) -> List[dict]:
    """
    COMPLETELY NEW APPROACH: Detect designs based on polyline outlines first,
    then associate small elements like circles with their parent designs.
    
    Args:
        msp: DXF modelspace
        
    Returns:
        List of design dictionaries with entities and bounds
    """
    print("  Detecting separate designs using outline-first approach...")
    entities = list(msp)
    
    if not entities:
        print("  No entities found")
        return []
        
    if len(entities) < 3:  # Very few entities, treat as one design
        print(f"  Only {len(entities)} entities, treating as single design")
        try:
            extents = msp.extents()
            if extents:
                min_x, min_y = extents.extmin.x, extents.extmin.y
                max_x, max_y = extents.extmax.x, extents.extmax.y
                return [{
                    'entities': entities,
                    'bounds': (min_x, min_y, max_x, max_y),
                    'width': max_x - min_x,
                    'height': max_y - min_y
                }]
        except Exception as e:
            print(f"  Error getting extents: {e}")
            return [{
                'entities': entities,
                'bounds': (0, 0, 1000, 1000),  # Default bounds
                'width': 1000,
                'height': 1000
            }]
    
    # Step 1: Categorize entities and get bounds
    polylines = []  # Main outline shapes
    small_circles = []  # Small circles (holes, mounting points)
    other_entities = []  # Other miscellaneous entities
    
    for entity in entities:
        try:
            if entity.dxftype() == 'LWPOLYLINE':
                points = list(entity.get_points())
                if points:
                    xs = [p[0] for p in points]
                    ys = [p[1] for p in points]
                    perimeter = entity.length()
                    polylines.append({
                        'entity': entity,
                        'min_x': min(xs),
                        'min_y': min(ys),
                        'max_x': max(xs),
                        'max_y': max(ys),
                        'center_x': (min(xs) + max(xs)) / 2,
                        'center_y': (min(ys) + max(ys)) / 2,
                        'perimeter': perimeter,
                        'area': (max(xs) - min(xs)) * (max(ys) - min(ys)),
                        'design_id': None  # Will be assigned later
                    })
            elif entity.dxftype() == 'CIRCLE' and entity.dxf.radius < 10:
                # Small circles are likely mounting holes
                center = entity.dxf.center
                small_circles.append({
                    'entity': entity,
                    'center_x': center[0],
                    'center_y': center[1],
                    'radius': entity.dxf.radius,
                    'design_id': None  # Will be assigned later
                })
            else:
                # Handle all other entities
                if hasattr(entity, 'bounds') and callable(entity.bounds):
                    try:
                        bounds = entity.bounds()
                        if bounds and len(bounds) == 2:
                            min_x, min_y = bounds[0]
                            max_x, max_y = bounds[1]
                            other_entities.append({
                                'entity': entity,
                                'min_x': min_x,
                                'min_y': min_y,
                                'max_x': max_x,
                                'max_y': max_y,
                                'center_x': (min_x + max_x) / 2,
                                'center_y': (min_y + max_y) / 2,
                                'design_id': None  # Will be assigned later
                            })
                            continue
                    except Exception as e:
                        pass
                
                # If bounds method failed, try type-specific handling
                if entity.dxftype() == 'LINE':
                    start, end = entity.dxf.start, entity.dxf.end
                    other_entities.append({
                        'entity': entity,
                        'min_x': min(start[0], end[0]),
                        'min_y': min(start[1], end[1]),
                        'max_x': max(start[0], end[0]),
                        'max_y': max(start[1], end[1]),
                        'center_x': (start[0] + end[0]) / 2,
                        'center_y': (start[1] + end[1]) / 2,
                        'design_id': None
                    })
        except Exception as e:
            print(f"  Error processing entity {entity.dxftype()}: {e}")
    
    # If no polylines found, use a simpler clustering approach
    if not polylines:
        print("  No polylines found, using simple clustering")
        
        # Combine all processable entities
        all_entities = other_entities + [{
            'entity': circle['entity'],
            'min_x': circle['center_x'] - circle['radius'],
            'min_y': circle['center_y'] - circle['radius'],
            'max_x': circle['center_x'] + circle['radius'],
            'max_y': circle['center_y'] + circle['radius'],
            'center_x': circle['center_x'],
            'center_y': circle['center_y'],
            'design_id': None
        } for circle in small_circles]
        
        if not all_entities:
            print("  No valid entities found")
            return [{
                'entities': entities,
                'bounds': (0, 0, 1000, 1000),
                'width': 1000,
                'height': 1000
            }]
        
        # Sort by x position
        all_entities.sort(key=lambda e: e['min_x'])
        
        # Simple clustering by X distance
        current_design = 0
        all_entities[0]['design_id'] = current_design
        for i in range(1, len(all_entities)):
            current = all_entities[i]
            previous = all_entities[i-1]
            
            # If horizontally far from previous entity, start new design
            if current['min_x'] - previous['max_x'] > 50:
                current_design += 1
            
            current['design_id'] = current_design
        
        # Create designs from clusters
        designs = []
        max_design_id = max(e['design_id'] for e in all_entities)
        
        for design_id in range(max_design_id + 1):
            design_entities = [e['entity'] for e in all_entities if e['design_id'] == design_id]
            if not design_entities:
                continue
            
            # Calculate bounds
            design_bounds = [e for e in all_entities if e['design_id'] == design_id]
            min_x = min(e['min_x'] for e in design_bounds)
            min_y = min(e['min_y'] for e in design_bounds)
            max_x = max(e['max_x'] for e in design_bounds)
            max_y = max(e['max_y'] for e in design_bounds)
            
            designs.append({
                'entities': design_entities,
                'bounds': (min_x, min_y, max_x, max_y),
                'width': max_x - min_x,
                'height': max_y - min_y
            })
        
        print(f"  Created {len(designs)} designs using simple clustering")
        return designs
    
    # Step 2: First, identify main designs from polylines
    # Sort polylines first by X position, then by area (descending)
    # This helps better organize designs left-to-right
    polylines.sort(key=lambda p: (p['min_x'], -p['area']))
    
    # Assign design IDs to polylines (main outlines)
    design_id = 0
    design_centers = []  # Track centers of main designs
    
    for poly in polylines:
        # Check if this polyline is already part of a design
        if poly['design_id'] is not None:
            continue
            
        # Check if this polyline is very close to an existing design
        is_close = False
        for x, y in design_centers:
            distance = ((poly['center_x'] - x) ** 2 + (poly['center_y'] - y) ** 2) ** 0.5
            # If close to existing design center, consider part of that design
            if distance < 100:  
                is_close = True
                break
        
        if not is_close:
            # New design found
            poly['design_id'] = design_id
            design_centers.append((poly['center_x'], poly['center_y']))
            design_id += 1
    
    if design_id == 0:
        # No distinct designs found, treat all as one design
        for poly in polylines:
            poly['design_id'] = 0
        design_id = 1
        
    print(f"  Identified {design_id} main designs from polylines")
    
    # Step 3: Assign small circles and other entities to nearest design
    # First, calculate bounds for each design
    design_bounds = {}
    for d_id in range(design_id):
        design_polys = [p for p in polylines if p['design_id'] == d_id]
        if design_polys:
            min_x = min(p['min_x'] for p in design_polys)
            min_y = min(p['min_y'] for p in design_polys)
            max_x = max(p['max_x'] for p in design_polys)
            max_y = max(p['max_y'] for p in design_polys)
            center_x = (min_x + max_x) / 2
            center_y = (min_y + max_y) / 2
            design_bounds[d_id] = {
                'min_x': min_x, 'min_y': min_y, 'max_x': max_x, 'max_y': max_y,
                'center_x': center_x, 'center_y': center_y
            }
    
    # Function to find closest design for an entity
    def find_closest_design(x, y):
        closest_id = 0
        min_dist = float('inf')
        
        for d_id, bounds in design_bounds.items():
            # Check if point is inside the design bounds (with small margin)
            if (bounds['min_x'] - 10 <= x <= bounds['max_x'] + 10 and 
                bounds['min_y'] - 10 <= y <= bounds['max_y'] + 10):
                return d_id
                
            # Calculate distance to center of design
            dist = ((x - bounds['center_x']) ** 2 + (y - bounds['center_y']) ** 2) ** 0.5
            if dist < min_dist:
                min_dist = dist
                closest_id = d_id
                
        return closest_id
    
    # Assign small circles to designs
    for circle in small_circles:
        circle['design_id'] = find_closest_design(circle['center_x'], circle['center_y'])
    
    # Assign other entities to designs
    for entity in other_entities:
        entity['design_id'] = find_closest_design(entity['center_x'], entity['center_y'])
    
    # Step 4: Create final design objects
    designs = []
    
    for d_id in range(design_id):
        # Collect all entities for this design
        design_entities = []
        design_entities.extend([p['entity'] for p in polylines if p['design_id'] == d_id])
        design_entities.extend([c['entity'] for c in small_circles if c['design_id'] == d_id])
        design_entities.extend([e['entity'] for e in other_entities if e['design_id'] == d_id])
        
        if not design_entities:
            continue
            
        # Calculate overall bounds for all entities in this design
        all_bounds = []
        all_bounds.extend([p for p in polylines if p['design_id'] == d_id])
        all_bounds.extend([{
            'min_x': c['center_x'] - c['radius'],
            'min_y': c['center_y'] - c['radius'],
            'max_x': c['center_x'] + c['radius'],
            'max_y': c['center_y'] + c['radius']
        } for c in small_circles if c['design_id'] == d_id])
        all_bounds.extend([e for e in other_entities if e['design_id'] == d_id])
        
        if not all_bounds:
            continue
            
        # Calculate bounds with slightly larger margins
        min_x = min(e['min_x'] for e in all_bounds) + 1  # Reduced left margin compression
        min_y = min(e['min_y'] for e in all_bounds) - 10  # Keep bottom margin for cut-off protection
        max_x = max(e['max_x'] for e in all_bounds) + 3  # Slightly larger right margin
        max_y = max(e['max_y'] for e in all_bounds) + 10  # Keep top margin for cut-off protection
        
        designs.append({
            'entities': design_entities,
            'bounds': (min_x, min_y, max_x, max_y),
            'width': max_x - min_x,
            'height': max_y - min_y,
            'id': d_id
        })
    
    print(f"  Created {len(designs)} final designs")
    return designs

def merge_dxf_files(dxf_paths: List[str], output_path: str) -> dict:
    """
    Advanced approach: Detect separate designs within each file and place them
    side-by-side with horizontal translation.
    
    Args:
        dxf_paths: List of paths to DXF files to merge
        output_path: Path where the merged DXF should be saved
    
    Returns:
        Dictionary containing:
        - path: Path to the merged DXF file
        - entity_colors: Dict mapping entity handles to colors based on source file
    """
    if not dxf_paths:
        raise ValueError("No DXF files provided for merging")
    
    if len(dxf_paths) == 1:
        # If only one file, just copy it
        import shutil
        shutil.copy(dxf_paths[0], output_path)
        # Still return in the same format for consistency
        return {
            'path': output_path,
            'entity_colors': {}  # No colors in single file case
        }
    
    # Create a completely new empty DXF document 
    merged_doc = ezdxf.new()
    merged_msp = merged_doc.modelspace()
    
    # Slightly less negative spacing for better separation
    spacing = -7  # Reduced negative spacing for more separation
    current_x_position = 0
    
    # Define a list of distinct colors for each source file - using bright, high-contrast colors
    colors = ['#FF0000', '#00FF00', '#0000FF', '#FFFF00', '#FF00FF', '#00FFFF', 
             '#FFA500', '#800080', '#008000', '#FFC0CB', '#00CED1', '#FF8C00']
    
    # Dictionary to track entity colors based on source file
    entity_colors = {}
    
    # Print color assignments for debugging
    print("Color assignments:")
    for i, dxf_path in enumerate(dxf_paths):
        file_name = Path(dxf_path).name
        color_idx = min(i, len(colors)-1)
        print(f"  File {i+1}: {file_name} → {colors[color_idx]}")
    
    print(f"=== Advanced approach: Detecting and placing designs side by side ===")
    
    # First copy all block definitions from all files
    for dxf_path in dxf_paths:
        try:
            source_doc = ezdxf.readfile(dxf_path)
            if hasattr(source_doc, 'blocks') and hasattr(merged_doc, 'blocks'):
                for block in source_doc.blocks:
                    try:
                        if block.name not in merged_doc.blocks:
                            merged_doc.blocks.new(name=block.name)
                            for entity in block:
                                merged_doc.blocks[block.name].add_entity(entity)
                    except Exception as e:
                        print(f"  Error copying block {block.name}: {e}")
        except Exception as e:
            print(f"  Error loading blocks from {dxf_path}: {e}")
    
    # Process each file and extract designs
    all_designs = []
    
    for idx, dxf_path in enumerate(dxf_paths):
        print(f"\n--- Processing file {idx + 1}: {dxf_path} ---")
        
        try:
            # Load source DXF
            source_doc = ezdxf.readfile(dxf_path)
            source_msp = source_doc.modelspace()
            
            # Detect separate designs within this file
            designs = detect_designs(source_msp)
            
            # Add file info to designs
            for i, design in enumerate(designs):
                design['file_index'] = idx + 1
                design['design_index'] = i + 1
                design['file_path'] = dxf_path
                print(f"  Design {i+1}: width={design['width']:.1f}, height={design['height']:.1f}")
                
            all_designs.extend(designs)
            
        except Exception as e:
            print(f"  ERROR processing file: {e}")
    
    print(f"\n=== Total designs to place: {len(all_designs)} ===")
    
    # Place each design side-by-side
    for design_idx, design in enumerate(all_designs):
        min_x, min_y, max_x, max_y = design['bounds']
        design_width = design['width']
        file_idx = design['file_index']
        
        print(f"\n--- Placing design {design_idx + 1} from file {file_idx} ---")
        print(f"  Original bounds: ({min_x:.1f}, {min_y:.1f}) to ({max_x:.1f}, {max_y:.1f})")
        print(f"  Placing at X position: {current_x_position:.1f}")
        
        # Calculate exact translation for perfect alignment
        translation_x = current_x_position - min_x
        print(f"  Translation needed: {translation_x:.1f}")
        
        # Copy and translate entities for this design
        success_count = 0
        error_count = 0
        
        for entity in design['entities']:
            try:
                if entity.dxftype() == 'LINE':
                    # Handle lines
                    start = entity.dxf.start
                    end = entity.dxf.end
                    new_entity = merged_msp.add_line(
                        (start[0] + translation_x, start[1], 0),
                        (end[0] + translation_x, end[1], 0)
                    )
                    
                    # Assign color to this entity based on source file index (0-based)
                    color_idx = min(file_idx, len(colors)-1)  # Use file index for color (0-based indexing)
                    file_color = colors[color_idx]
                    entity_colors[new_entity.dxf.handle] = file_color
                    success_count += 1
                
                elif entity.dxftype() == 'LWPOLYLINE':
                    # Handle lightweight polylines
                    points = []
                    for point in entity.get_points():
                        x, y = point[0], point[1]
                        points.append((x + translation_x, y))
                    
                    poly = merged_msp.add_lwpolyline(points)
                    if hasattr(entity.dxf, 'closed'):
                        poly.dxf.closed = entity.dxf.closed
                    
                    # Assign color to this entity based on source file index (0-based)
                    color_idx = min(file_idx, len(colors)-1)
                    file_color = colors[color_idx]
                    entity_colors[poly.dxf.handle] = file_color
                    success_count += 1
                
                elif entity.dxftype() == 'CIRCLE':
                    # Handle circles
                    center = entity.dxf.center
                    circle = merged_msp.add_circle(
                        (center[0] + translation_x, center[1], 0),
                        entity.dxf.radius
                    )
                    
                    # Assign color to this entity based on source file index (0-based)
                    color_idx = min(file_idx, len(colors)-1)
                    file_color = colors[color_idx]
                    entity_colors[circle.dxf.handle] = file_color
                    success_count += 1
                
                elif entity.dxftype() == 'ARC':
                    # Handle arcs
                    center = entity.dxf.center
                    arc = merged_msp.add_arc(
                        (center[0] + translation_x, center[1], 0),
                        entity.dxf.radius,
                        entity.dxf.start_angle,
                        entity.dxf.end_angle
                    )
                    
                    # Assign color to this entity based on source file index (0-based)
                    color_idx = min(file_idx, len(colors)-1)
                    file_color = colors[color_idx]
                    entity_colors[arc.dxf.handle] = file_color
                    success_count += 1
                
                elif entity.dxftype() == 'INSERT':
                    # Handle block references/inserts
                    insert_point = entity.dxf.insert
                    insert = merged_msp.add_blockref(
                        entity.dxf.name,
                        (insert_point[0] + translation_x, insert_point[1], 0)
                    )
                    
                    # Assign color to this entity based on source file index (0-based)
                    color_idx = min(file_idx, len(colors)-1)
                    file_color = colors[color_idx]
                    entity_colors[insert.dxf.handle] = file_color
                    success_count += 1
                
                elif entity.dxftype() == 'TEXT':
                    # Handle text entities
                    position = entity.dxf.insert
                    text = merged_msp.add_text(
                        entity.dxf.text,
                        dxfattribs={
                            'insert': (position[0] + translation_x, position[1], 0),
                            'height': entity.dxf.height
                        }
                    )
                    
                    # Assign color to this entity based on source file index (0-based)
                    color_idx = min(file_idx, len(colors)-1)
                    file_color = colors[color_idx]
                    entity_colors[text.dxf.handle] = file_color
                    success_count += 1
                
                else:
                    print(f"  Skipping entity type: {entity.dxftype()}")
                    error_count += 1
            
            except Exception as e:
                print(f"  Error processing entity {entity.dxftype()}: {e}")
                error_count += 1
        
        print(f"  Copied {success_count} entities successfully, {error_count} errors")
        
        # Update position for next design - use 65% of design width for better spacing
        effective_width = design_width * 0.65  # Use 65% of actual width for slightly more separation
        current_x_position += effective_width + spacing
        print(f"  Next design will start at x={current_x_position:.1f} (using effective width: {effective_width:.1f})")
    
    # Save the merged document
    merged_doc.saveas(output_path)
    print(f"\n=== Merged DXF saved to: {output_path} ===")
    print(f"Total width: {current_x_position:.1f}")
    print(f"Total entities colored: {len(entity_colors)}")
    
    return {
        'path': output_path,
        'entity_colors': entity_colors
    }

def merge_and_convert_dxf_to_jpg(dxf_paths: List[str], merged_dxf_path: str, jpg_path: str, dpi: int = 600) -> dict:
    """
    Merge multiple DXF files and convert the result to JPG with high quality.
    Each source file's entities will have a unique color in the JPG output.
    
    Args:
        dxf_paths: List of paths to DXF files to merge
        merged_dxf_path: Path where the merged DXF should be saved
        jpg_path: Path where the JPG should be saved
        dpi: Output resolution (increased to 400 for better quality)
    
    Returns:
        Dictionary with merged_dxf_path and jpg_path
    """
    # First merge all DXF files - now returns dict with path and color mappings
    merge_result = merge_dxf_files(dxf_paths, merged_dxf_path)
    merged_dxf_path = merge_result['path']
    entity_colors = merge_result['entity_colors']
    
    # Then convert to JPG with higher resolution and color mapping
    convert_dxf_to_jpg(merged_dxf_path, jpg_path, dpi, color_map=entity_colors)
    
    return {
        'merged_dxf_path': merged_dxf_path,
        'jpg_path': jpg_path
    }
