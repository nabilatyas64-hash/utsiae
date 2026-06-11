import os
import time
from flask import Flask, jsonify, request
from models import db, Inventory
from dotenv import load_dotenv
from sqlalchemy.exc import OperationalError

load_dotenv()

app = Flask(__name__)

# Config database
db_host = os.getenv('DB_HOST', 'localhost')
db_name = os.getenv('DB_NAME', 'laundry_inventory_db')
db_user = os.getenv('DB_USER', 'root')
db_password = os.getenv('DB_PASSWORD', 'root')
db_port = os.getenv('DB_PORT', '3306')

app.config['SQLALCHEMY_DATABASE_URI'] = f"mysql+pymysql://{db_user}:{db_password}@{db_host}:{db_port}/{db_name}"
app.config['SQLALCHEMY_TRACK_MODIFICATIONS'] = False

db.init_app(app)

# Create tables with retry to handle DB startup delays in Docker Compose
with app.app_context():
    for attempt in range(10):
        try:
            db.create_all()
            print("Inventory Service database tables created/connected successfully.")
            break
        except OperationalError:
            print(f"Inventory Database not ready, retrying in 3 seconds... ({attempt+1}/10)")
            time.sleep(3)

@app.route('/inventory', methods=['GET'])
def get_inventories():
    items = Inventory.query.all()
    return jsonify([item.to_dict() for item in items]), 200

@app.route('/inventory/<int:id>', methods=['GET'])
def get_inventory(id):
    item = db.session.get(Inventory, id)
    if not item:
        return jsonify({'message': 'Inventory item not found'}), 404
    return jsonify(item.to_dict()), 200

@app.route('/inventory', methods=['POST'])
def create_inventory():
    data = request.get_json() or {}
    
    item_name = data.get('item_name')
    stock = data.get('stock', 0)
    unit = data.get('unit')
    status = data.get('status')
    
    if not item_name or not unit:
        return jsonify({'message': 'item_name and unit are required'}), 400
        
    try:
        stock = int(stock)
        if stock < 0:
            return jsonify({'message': 'Stock cannot be negative'}), 400
    except ValueError:
        return jsonify({'message': 'Stock must be a valid integer'}), 400
        
    if not status:
        status = 'out of stock' if stock == 0 else 'available'
        
    item = Inventory(
        item_name=item_name,
        stock=stock,
        unit=unit,
        status=status
    )
    db.session.add(item)
    db.session.commit()
    
    return jsonify({
        'message': 'Inventory item created successfully',
        'data': item.to_dict()
    }), 201

@app.route('/inventory/<int:id>', methods=['PUT'])
def update_inventory(id):
    item = db.session.get(Inventory, id)
    if not item:
        return jsonify({'message': 'Inventory item not found'}), 404
        
    data = request.get_json() or {}
    
    item_name = data.get('item_name')
    stock = data.get('stock')
    unit = data.get('unit')
    status = data.get('status')
    
    if item_name is not None:
        item.item_name = item_name
        
    if stock is not None:
        try:
            stock = int(stock)
            if stock < 0:
                return jsonify({'message': 'Stock cannot be negative'}), 400
            item.stock = stock
            # Auto-adjust status based on stock if status was not explicitly provided
            if status is None:
                if item.stock == 0:
                    item.status = 'out of stock'
                else:
                    item.status = 'available'
        except ValueError:
            return jsonify({'message': 'Stock must be a valid integer'}), 400
            
    if unit is not None:
        item.unit = unit
        
    if status is not None:
        item.status = status
        
    db.session.commit()
    
    return jsonify({
        'message': 'Inventory item updated successfully',
        'data': item.to_dict()
    }), 200

@app.route('/inventory/<int:id>', methods=['DELETE'])
def delete_inventory(id):
    item = db.session.get(Inventory, id)
    if not item:
        return jsonify({'message': 'Inventory item not found'}), 404
        
    db.session.delete(item)
    db.session.commit()
    
    return jsonify({'message': 'Inventory item deleted successfully'}), 200

@app.route('/inventory/reduce', methods=['POST'])
def reduce_stock():
    data = request.get_json() or {}
    item_id = data.get('item_id')
    quantity = data.get('quantity')
    
    if item_id is None or quantity is None:
        return jsonify({'message': 'item_id and quantity are required'}), 400
        
    try:
        quantity = int(quantity)
        if quantity <= 0:
            return jsonify({'message': 'Quantity must be greater than 0'}), 400
    except ValueError:
        return jsonify({'message': 'Quantity must be a valid integer'}), 400
        
    item = db.session.get(Inventory, item_id)
    if not item:
        return jsonify({'message': f'Inventory item with ID {item_id} not found'}), 404
        
    if item.stock < quantity:
        return jsonify({
            'message': f'Insufficient stock. Available: {item.stock}, Requested: {quantity}',
            'status': 'error'
        }), 400
        
    item.stock -= quantity
    if item.stock == 0:
        item.status = 'out of stock'
        
    db.session.commit()
    
    return jsonify({
        'message': f'Stock reduced successfully by {quantity}',
        'data': item.to_dict()
    }), 200

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000)
